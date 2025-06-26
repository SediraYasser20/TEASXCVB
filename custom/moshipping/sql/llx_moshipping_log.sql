-- ============================================================================
-- Copyright (C) 2024 Your Company
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see https://www.gnu.org/licenses/.

CREATE TABLE llx_moshipping_log (
    rowid           integer AUTO_INCREMENT PRIMARY KEY,
    date_creation   datetime NOT NULL,
    fk_user         integer,
    fk_shipment     integer,
    mo_ref          varchar(128),
    original_product integer,
    replaced_product integer,
    qty             double,
    note            text
) ENGINE=innodb;