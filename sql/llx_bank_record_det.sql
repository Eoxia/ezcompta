-- ===================================================================
-- Copyright (C) 2026 Laurent MAGNIN <laurent.magnin@evarisk.com>
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
-- along with this program. If not, see <https://www.gnu.org/licenses/>.
--
-- ===================================================================

CREATE TABLE IF NOT EXISTS llx_bank_record_det
(
  rowid                     integer AUTO_INCREMENT PRIMARY KEY,
  fk_bank_record            integer NOT NULL,
  bdate                     date NOT NULL,
  vdate                     date NOT NULL,
  amount                    double(24,8) NOT NULL,
  currency_code             varchar(3) DEFAULT 'EUR',
  charges_amount            double(24,8) DEFAULT 0,
  end_to_end_id             varchar(64) NULL,
  acct_svcr_ref             varchar(64) NULL,
  mandate_id                varchar(64) NULL,
  check_number              varchar(32) NULL,
  counterparty_name         varchar(255) NULL,
  counterparty_iban         varchar(34) NULL,
  counterparty_bic          varchar(11) NULL,
  label                     varchar(255) NOT NULL,
  remittance_unstructured   text NULL,
  remittance_structured     varchar(140) NULL,
  bank_tx_code              varchar(16) NULL,
  proprietary_code          varchar(32) NULL,
  fk_soc                    integer NULL,
  fk_bank                   integer NULL,
  status                    smallint DEFAULT 0 NOT NULL,
  datas                     text NULL,
  import_key                varchar(14) NULL,
  tms                       timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
