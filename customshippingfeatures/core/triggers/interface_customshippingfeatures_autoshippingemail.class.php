<?php

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php'; // For type hinting and object properties
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';     // For fetching contact emails
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';     // For fetching thirdparty info

/**
 * Trigger to automatically send an email with the Espadon PDF when a shipment is validated.
 */
class InterfaceCustomShippingFeaturesAutoShippingEmail extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db);

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->description = "Automatically sends Espadon PDF via email upon shipment validation.";
        $this->version = '1.0'; // Or use self::VERSIONS['prod'] if stable
        $this->family = "logistic";
        $this->picto = 'dolly';
    }

    /**
     * Trigger function executed when a Dolibarr business event is done.
     *
     * @param string       $action     Event action code
     * @param CommonObject $object     Object (Shipment in this case)
     * @param User         $user       Object user
     * @param Translate    $langs      Object langs
     * @param Conf         $conf       Object conf
     * @return int                     <0 if KO, 0 if no trigger ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        dol_syslog(__METHOD__ . " received action: " . $action . " for object ID: " . (isset($object->id) ? $object->id : 'N/A'), LOG_DEBUG);

        if ($action == 'SHIPPING_VALIDATE') {
            dol_syslog(__METHOD__ . " SHIPPING_VALIDATE trigger fired for shipment ID: " . $object->id, LOG_INFO);

            if (!$object instanceof Expedition) {
                dol_syslog(__METHOD__ . " Error: Object is not an instance of Expedition.", LOG_ERR);
                $this->error = "Object is not an instance of Expedition.";
                return -1;
            }

            /** @var Expedition $shipment */
            $shipment = $object;

            // 1. Ensure PDF "espadon" is available
            // The core should generate it right after validation if MAIN_DISABLE_PDF_AUTOUPDATE is not set.
            // If it might not be, we might need to generate it.
            // For now, assume it's generated.

            $pdfModel = 'espadon'; // The required model
            // If the shipment's model_pdf is not espadon, we might choose to not send, or force generate espadon.
            // For now, we'll only proceed if the default model is espadon or if we can reliably generate it.
            // Let's assume we want to generate 'espadon' regardless of the default.

            $outputlangs = $langs; // Use current langs or determine specific lang for the PDF/email
            // Potentially load specific language for the thirdparty
            if (getDolGlobalInt('MAIN_MULTILANGS') && !empty($shipment->thirdparty->default_lang)) {
                $outputlangs = new Translate("", $conf);
                $outputlangs->setDefaultLang($shipment->thirdparty->default_lang);
                $outputlangs->loadLangs(array("sendings", "main", "companies", "products", "deliveries")); // Load necessary lang files
            }

            // Regenerate the document with the 'espadon' model to ensure we have it.
            // Note: generateDocument might trigger its own hooks.
            // The result of generateDocument contains the full path in $shipment->result['fullpath']
            // However, the standard naming convention is also predictable.

            $shipmentRefSanitized = dol_sanitizeFileName($shipment->ref);
            $expectedPdfPath = $conf->expedition->dir_output . "/sending/" . $shipmentRefSanitized . "/" . $shipmentRefSanitized . "_garantie.pdf"; // This is the specific name from pdf_espadon.modules.php

            // Ensure the document exists or try to generate it
            if (!dol_is_file($expectedPdfPath)) {
                dol_syslog(__METHOD__ . " PDF not found at " . $expectedPdfPath . ". Attempting to generate.", LOG_INFO);
                $result_gen = $shipment->generateDocument($pdfModel, $outputlangs, 0, 0, 0);
                if ($result_gen <= 0) {
                    dol_syslog(__METHOD__ . " Error generating Espadon PDF for shipment ID: " . $shipment->id . ". Error: " . $shipment->error, LOG_ERR);
                    $this->error = "Failed to generate Espadon PDF: " . $shipment->error;
                    return -2;
                }
                // After generation, the path might be in $shipment->result['fullpath']
                // but generateDocument also often saves it using the standard name.
                // We'll re-check the expected path.
                 if (!dol_is_file($expectedPdfPath)) {
                    // If using $shipment->result['fullpath'] after generation:
                    // $pdfFilePath = $shipment->result['fullpath'];
                    // if (!dol_is_file($pdfFilePath)) { ... }
                    dol_syslog(__METHOD__ . " Error: Espadon PDF still not found at " . $expectedPdfPath . " after attempting generation.", LOG_ERR);
                    $this->error = "Espadon PDF not found after generation attempt.";
                    return -3;
                 }
            }
            $pdfFilePath = $expectedPdfPath;
            $pdfFileNameForEmail = $shipmentRefSanitized . "_garantie.pdf";


            // 2. Fetch Client's Email
            if (empty($shipment->thirdparty) || empty($shipment->thirdparty->id)) {
                $shipment->fetch_thirdparty();
            }

            if (empty($shipment->thirdparty->id)) {
                dol_syslog(__METHOD__ . " Error: Thirdparty not loaded for shipment ID: " . $shipment->id, LOG_ERR);
                $this->error = "Thirdparty not found for shipment.";
                return -4;
            }

            $recipientEmail = '';
            // Try to get a specific contact type for shipping, otherwise fall back.
            // You might need to define a constant or a setup variable for CONTACT_TYPE_SHIPPING_NOTIFICATION
            // For example, 'SHIPPING' or 'CUSTOMER'
            $contactTypeCodes = array('SHIPPING', 'CUSTOMER'); // Prioritized list of contact types

            foreach ($contactTypeCodes as $contactCode) {
                $contacts = $shipment->thirdparty->liste_contact(-1, 'external', 0, $contactCode);
                if (!empty($contacts)) {
                    foreach ($contacts as $contact) {
                        if (!empty($contact['email']) && filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                            $recipientEmail = $contact['email'];
                            dol_syslog(__METHOD__ . " Found recipient email from contact type ".$contactCode.": " . $recipientEmail, LOG_DEBUG);
                            break 2; // Found email, break both loops
                        }
                    }
                }
            }

            // Fallback to thirdparty's main email if no specific contact email was found
            if (empty($recipientEmail) && !empty($shipment->thirdparty->email) && filter_var($shipment->thirdparty->email, FILTER_VALIDATE_EMAIL)) {
                $recipientEmail = $shipment->thirdparty->email;
                dol_syslog(__METHOD__ . " Using thirdparty main email: " . $recipientEmail, LOG_DEBUG);
            }

            if (empty($recipientEmail)) {
                dol_syslog(__METHOD__ . " Error: No valid recipient email found for thirdparty ID: " . $shipment->thirdparty->id . " on shipment ID: " . $shipment->id, LOG_ERR);
                $this->error = "No valid recipient email found for the client.";
                return -5; // No email to send to
            }

            // 3. Compose Email
            $companyName = $conf->global->MAIN_INFO_SOCIETE_NOM;
            $clientName = $shipment->thirdparty->name;

            $emailSubject = $langs->transnoentitiesnoconv("ShipmentValidatedSubject", $shipment->ref, $companyName);
            if ($emailSubject == "ShipmentValidatedSubject") { // If translation is missing
                 $emailSubject = "Shipment ".$shipment->ref." from ".$companyName." has been validated";
            }

            $emailBody = $langs->transnoentitiesnoconv("ShipmentValidatedBody", $clientName, $shipment->ref, $companyName);
            if ($emailBody == "ShipmentValidatedBody") { // If translation is missing
                $emailBody = "Dear ".$clientName.",\n\n";
                $emailBody .= "Your shipment with reference ".$shipment->ref." has been validated.\n";
                $emailBody .= "Please find the shipping document attached.\n\n";
                $emailBody .= "Regards,\n";
                $emailBody .= $companyName;
            }

            // Use an appropriate sender email from Dolibarr's configuration
            $senderEmail = !empty($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : $user->email;
            if (empty($senderEmail)) {
                 dol_syslog(__METHOD__ . " Error: Sender email (MAIN_MAIL_EMAIL_FROM or current user) is not configured.", LOG_ERR);
                 $this->error = "Sender email not configured.";
                 return -6;
            }
            $senderName = !empty($conf->global->MAIN_INFO_SOCIETE_NOM) ? $conf->global->MAIN_INFO_SOCIETE_NOM : 'Dolibarr';
            $from = '"'.dol_string_nohtmltag($senderName, 1).'" <'.$senderEmail.'>';


            // 4. Send Email using CMailFile
            $mailfile = new CMailFile(
                $emailSubject,
                $recipientEmail,
                $from,
                $emailBody,
                array($pdfFilePath),                // $filename_list (array of full paths)
                array('application/pdf'),           // $mimetype_list
                array($pdfFileNameForEmail),        // $mimefilename_list (name in email)
                '',                                 // CC
                '',                                 // BCC
                0,                                  // deliveryreceipt
                0,                                  // $msgishtml (0 for plain text, 1 for HTML)
                '',                                 // errors_to
                '',                                 // css
                'shipment'.$shipment->id,           // trackid
                '',                                 // moreinheader
                'standard',                         // sendcontext
                $from                               // replyto
            );

            if ($mailfile->error) {
                dol_syslog(__METHOD__ . " Error creating CMailFile object: " . $mailfile->error, LOG_ERR);
                $this->error = "Failed to create mail object: " . $mailfile->error;
                $this->errors = array_merge($this->errors, $mailfile->errors);
                return -7;
            }

            $result = $mailfile->sendfile();
            if ($result) {
                dol_syslog(__METHOD__ . " Successfully sent Espadon PDF for shipment ID: " . $shipment->id . " to " . $recipientEmail, LOG_INFO);
                return 1; // Success
            } else {
                dol_syslog(__METHOD__ . " Error sending email for shipment ID: " . $shipment->id . ". CMailFile Error: " . $mailfile->error, LOG_ERR);
                $this->error = "Failed to send email: " . $mailfile->error;
                $this->errors = array_merge($this->errors, $mailfile->errors);
                // It's often better to not stop the whole validation process if only email fails.
                // So, we log the error but return 0 (success for trigger, but action not fully completed by this trigger)
                // or a positive value if we consider the trigger's main job done despite email failure.
                // Returning negative would rollback the shipment validation.
                return 0; // Indicate trigger ran but email failed, allowing main process to continue.
            }
        }
        return 0; // Action not concerned by this trigger
    }
}

?>
