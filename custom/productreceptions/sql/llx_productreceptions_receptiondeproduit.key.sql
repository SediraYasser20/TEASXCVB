-- Copyright (C) 2025		SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


-- BEGIN MODULEBUILDER INDEXES
ALTER TABLE llx_productreceptions_receptiondeproduit ADD INDEX idx_productreceptions_receptiondeproduit_rowid (rowid);
ALTER TABLE llx_productreceptions_receptiondeproduit ADD INDEX idx_productreceptions_receptiondeproduit_fk_soc (fk_soc);
ALTER TABLE llx_productreceptions_receptiondeproduit ADD INDEX idx_productreceptions_receptiondeproduit_fk_project (fk_project);
-- END MODULEBUILDER INDEXES

--ALTER TABLE llx_productreceptions_receptiondeproduit ADD UNIQUE INDEX uk_productreceptions_receptiondeproduit_fieldxy(fieldx, fieldy);

--ALTER TABLE llx_productreceptions_receptiondeproduit ADD CONSTRAINT llx_productreceptions_receptiondeproduit_fk_field FOREIGN KEY (fk_field) REFERENCES llx_productreceptions_myotherobject(rowid);
