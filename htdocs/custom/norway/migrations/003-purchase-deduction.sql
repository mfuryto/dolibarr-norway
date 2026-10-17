ALTER TABLE llx_facture_fourn_det_extrafields ADD COLUMN IF NOT EXISTS norway_vat_deduction DECIMAL(7,2) NULL;
INSERT INTO llx_extrafields (name,entity,elementtype,label,type,size,pos,alwayseditable,emptyonclone,list,printable,langs,help,enabled)
SELECT 'norway_vat_deduction',0,'facture_fourn_det','NorwayVatDeduction','double','7,2',900,0,0,'3',0,'norway@norway','NorwayVatDeductionHelp','isModEnabled("norway")'
WHERE NOT EXISTS (SELECT 1 FROM llx_extrafields WHERE name='norway_vat_deduction' AND elementtype='facture_fourn_det');
