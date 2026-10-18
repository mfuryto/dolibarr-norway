#!/usr/bin/env python3
"""Validate synthetic EHF fixtures with pinned official XSD/rules and source comparisons."""
import argparse
import hashlib
import json
from decimal import Decimal
from pathlib import Path
from urllib.request import Request, urlopen
from lxml import etree
from saxonche import PySaxonProcessor

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--cache', type=Path, required=True)
parser.add_argument('--documents', type=Path, required=True)
args = parser.parse_args()
manifest = json.loads((Path(__file__).resolve().parents[1] / 'tests/ehf/validation-manifest.json').read_text())
root = args.cache.resolve()
root.mkdir(parents=True, exist_ok=True)
for url, checksum in manifest['files']:
    relative = 'ubl/' + url.split('/xsd/')[1] if '/xsd/' in url else url.rsplit('/', 1)[1]
    target = root / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    raw = target.read_bytes() if target.is_file() else b''
    if hashlib.sha256(raw).hexdigest() != checksum:
        with urlopen(Request(url, headers={'User-Agent': 'Norway-EHF-qualification'}), timeout=40) as response:
            raw = response.read(8 * 1024 * 1024)
        if hashlib.sha256(raw).hexdigest() != checksum:
            raise SystemExit('Official asset checksum changed: ' + url)
        target.write_bytes(raw)

names = ['PEPPOL-EN16931-UBL', 'CEN-EN16931-UBL']
ns = {'cac': 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
      'cbc': 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2'}
failures = []
validated = blocked = 0
with PySaxonProcessor(license=False) as processor:
    xslt = processor.new_xslt30_processor()
    validators = []
    for name in names:
        source = root / (name + '.sch')
        for stage in ['iso_dsdl_include', 'iso_abstract_expand', 'iso_svrl_for_xslt2']:
            target = root / (name + '-' + stage + '.xsl')
            xslt.transform_to_file(source_file=str(source), stylesheet_file=str(root / (stage + '.xsl')), output_file=str(target))
            source = target
        validators.append(xslt.compile_stylesheet(stylesheet_file=str(source)))
    for expectation in sorted(args.documents.glob('*.json')):
        fixture = json.loads(expectation.read_text())
        path = expectation.with_suffix('.xml')
        issues = []
        if fixture.get('blocked'):
            if path.exists():
                issues.append('Blocked source still produced XML')
            blocked += 1
        else:
            raw = path.read_bytes()
            if b'<!DOCTYPE' in raw.upper() or b'<!ENTITY' in raw.upper():
                raise SystemExit('DTD/entity declarations are forbidden')
            tree = etree.fromstring(raw, etree.XMLParser(no_network=True, resolve_entities=False))
            kind = etree.QName(tree).localname
            if kind not in ['Invoice', 'CreditNote']:
                raise SystemExit('Unexpected document type')
            schema = etree.XMLSchema(etree.parse(str(root / 'ubl/maindoc' / ('UBL-' + kind + '-2.1.xsd'))))
            if not schema.validate(tree):
                issues.extend(error.message for error in schema.error_log)
            for validator in validators:
                svrl = etree.fromstring(validator.transform_to_string(source_file=str(path.resolve())).encode())
                for issue in svrl.findall('.//{http://purl.oclc.org/dsdl/svrl}failed-assert'):
                    if issue.get('flag') != 'warning':
                        issues.append(issue.get('id') + ': ' + ' '.join(issue.itertext()).strip())
            def value(xpath):
                return tree.xpath('string(' + xpath + ')', namespaces=ns)
            factor = -1 if fixture['credit'] else 1
            for field, node in [('net', 'TaxExclusiveAmount'), ('gross', 'TaxInclusiveAmount'), ('gross', 'PayableAmount')]:
                if Decimal(value('/*/cac:LegalMonetaryTotal/cbc:' + node)) != Decimal(fixture['native_' + field]) * factor:
                    issues.append('Source ' + node + ' mismatch')
            if Decimal(value('/*/cac:TaxTotal/cbc:TaxAmount')) != Decimal(fixture['native_tax']) * factor:
                issues.append('Source VAT mismatch')
            if value('/*/cbc:BuyerReference') != fixture['expected_buyer_reference']:
                issues.append('Buyer reference mismatch')
            if value('/*/cac:OrderReference/cbc:ID') != fixture.get('expected_order_reference', ''):
                issues.append('Customer purchase order mismatch')
            if fixture['credit'] and value('/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID') != fixture['expected_original_reference']:
                issues.append('Original invoice reference mismatch')
            for party, endpoint in [('AccountingSupplierParty', '0192:123456785'), ('AccountingCustomerParty', fixture['expected_recipient'])]:
                prefix = '/*/cac:' + party + '/cac:Party/cbc:EndpointID'
                if value(prefix + '/@schemeID') + ':' + value(prefix) != endpoint:
                    issues.append('Participant mismatch: ' + party)
            validated += 1
        if issues:
            failures.append({'scenario': fixture['scenario'], 'issues': issues})
    if not validated or not blocked:
        raise SystemExit('Both positive and blocked synthetic cases are required')
print(json.dumps({'validated': validated, 'blocked': blocked, 'failures': failures}, ensure_ascii=False, indent=2))
raise SystemExit(bool(failures))
