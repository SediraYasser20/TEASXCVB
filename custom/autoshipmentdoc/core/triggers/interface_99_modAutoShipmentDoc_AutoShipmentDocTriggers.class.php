<?php

require_once DOL_DOCUMENT_ROOT . '/core/interfaces/TriggerManager.interface.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/cmailfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/sendings.lib.php';

class InterfaceAutoShipmentDocTriggers implements TriggerManagerInterface
{
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        global $db;

        if ($action == 'SHIPPING_VALIDATE' && $object instanceof Expedition) {
            dol_syslog("Trigger AutoShipmentDoc: SHIPPING_VALIDATE for shipment " . $object->ref, LOG_INFO);

            // Generate PDF
            $hidedetails = 0;
            $hidedesc = 0;
            $hideref = 0;

            $model = 'espadon'; // TODO: Make this configurable?

            // Ensure output lang is loaded
            $outputlangs = $langs;
            if (!empty($conf->global->MAIN_MULTILANGS) && !empty($object->thirdparty->default_lang)) {
                $outputlangs = new Translate("", $conf);
                $outputlangs->setDefaultLang($object->thirdparty->default_lang);
            }


            $result = $object->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);
            if ($result <= 0) {
                dol_syslog("Trigger AutoShipmentDoc: Failed to generate PDF for shipment " . $object->ref . ". Error: " . $object->error, LOG_ERR);
                return -1; // PDF generation failed
            }

            $filename = $object->dir_output . '/' . $object->ref . '.pdf'; // Path to the generated PDF

            // Send email
            $sendto = $object->thirdparty->email;
            if (empty($sendto)) {
                dol_syslog("Trigger AutoShipmentDoc: Client email is empty for shipment " . $object->ref, LOG_ERR);
                return -1; // Client email empty
            }

            $from = $conf->global->MAIN_MAIL_EMAIL_FROM;
            $replyto = $conf->global->MAIN_MAIL_EMAIL_REPLYTO;
            $subject = $langs->transnoentities("ShipmentConfirmation") . ": " . $object->ref;
            $message = $langs->transnoentities("PleaseFindYourShipmentConfirmationAttached");

            $mailfile = new CMailFile($subject, $sendto, $from, $replyto, $filename, array(), array(), '', '', 0, -1);

            if ($mailfile->sendfile()) {
                dol_syslog("Trigger AutoShipmentDoc: Email sent successfully for shipment " . $object->ref, LOG_INFO);
                // Add a record for the sent email
                sendings_add_object_linked($object, $mailfile);

            } else {
                dol_syslog("Trigger AutoShipmentDoc: Failed to send email for shipment " . $object->ref . ". Error: " . $mailfile->error, LOG_ERR);
                // Set error message for Dolibarr UI if possible/needed
                // $object->error = $langs->trans("ErrorFailedToSendEmail").": ".$mailfile->error;
                return -1; // Email sending failed
            }

            return 0; // Success
        }

        return 0; // Default return if action is not handled
    }

    public function applyActions($action, $object, $user, $langs, $conf) {
        // This method is deprecated and/or not used by modern triggers.
        // Kept for compatibility with older Dolibarr versions if necessary.
        return $this->runTrigger($action, $object, $user, $langs, $conf);
    }
}
?>
