-- ============================================================
--  CASM update: personal details for administrators and lecturers
--
--  Only needed if you ALREADY imported casm.sql before.
--  It fills in sample details (phone, IC, address ...) for the
--  demo administrator and lecturers, and ONLY where the address is
--  still empty, so anything you already typed is never overwritten.
--  No table changes are needed: the columns already exist.
--
--  phpMyAdmin > click database "casm_db" > SQL tab > paste > Go
-- ============================================================
USE casm_db;

UPDATE users SET phone='03-77412200', ic_number='800101-14-5001', date_of_birth='1980-01-01', gender='male', address='Level 2, Block A, CASM Campus, Jalan Universiti', postcode='46200', city='Petaling Jaya', state='Selangor' WHERE email='admin@casm.test' AND address IS NULL;
UPDATE users SET phone='012-9081234', ic_number='850615-10-5342', date_of_birth='1985-06-15', gender='female', address='18 Jalan SS 15/4, Subang Jaya', postcode='47500', city='Subang Jaya', state='Selangor', programme='Faculty of Computing' WHERE email='lecturer1@casm.test' AND address IS NULL;
UPDATE users SET phone='016-2273345', ic_number='881103-14-5237', date_of_birth='1988-11-03', gender='male', address='22 Jalan Bukit Bintang 3, Taman Segar', postcode='55100', city='Kuala Lumpur', state='Kuala Lumpur', programme='Faculty of Computing' WHERE email='lecturer2@casm.test' AND address IS NULL;
