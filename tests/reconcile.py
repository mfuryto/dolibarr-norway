"""Independent test oracle; not a production bookkeeping or tax engine."""
from collections import defaultdict
from datetime import date


def reconcile(data):
    assert data['synthetic'] is True and data['currency'] == 'NOK'
    start, end = map(date.fromisoformat, data['period'])
    assert start <= end
    documents = {d['id']: d for d in data['documents']}
    assert len(documents) == len(data['documents']), 'duplicate document'
    opening, closing, bases, taxes, paid = (defaultdict(int) for _ in range(5))
    for doc in data['documents']:
        when = date.fromisoformat(doc['date'])
        assert when <= end
        assert sum(l['debit'] - l['credit'] for l in doc['lines']) == 0, 'unbalanced document'
        for line in doc['lines']:
            assert type(line['debit']) is int and type(line['credit']) is int
            assert line['debit'] >= 0 and line['credit'] >= 0
            assert not (line['debit'] and line['credit'])
            amount = line['debit'] - line['credit']
            closing[line['account']] += amount
            if when < start:
                opening[line['account']] += amount
        if doc['kind'] in ('sale', 'purchase', 'credit'):
            assert doc['net'] + doc['vat'] == doc['gross'], 'gross amount'
            if data['profile'] == 'registered':
                assert doc['vat'] * 4 == doc['net'], 'synthetic 25% VAT'
                bases[doc['tax_code']] += doc['net']
                taxes[doc['tax_code']] += doc['vat']
                account = '2710' if doc['kind'] == 'purchase' else '2700'
                ledger_vat = sum(l['debit'] - l['credit'] for l in doc['lines'] if l['account'] == account)
                assert ledger_vat == (doc['vat'] if account == '2710' else -doc['vat']), 'VAT ledger mismatch'
            else:
                assert not any(l['account'] in ('2700', '2710') for l in doc['lines']), 'unregistered tax deduction/collection'
                if doc['kind'] in ('sale', 'credit'):
                    assert doc['vat'] == 0
                else:
                    assert sum(l['debit'] for l in doc['lines'] if l['account'] == '6800') == doc['gross']
        if doc['kind'] == 'credit':
            assert doc['source'] in documents, 'missing credit source'
            assert documents[doc['source']]['kind'] == 'sale'
            assert doc['net'] < 0 and doc['gross'] < 0
        if doc['kind'] in ('receipt', 'disbursement'):
            assert doc['source'] in documents, 'missing payment source'
            original = documents[doc['source']]
            assert original['kind'] == ('sale' if doc['kind'] == 'receipt' else 'purchase')
            assert doc['amount'] > 0
            paid[doc['source']] += doc['amount']
            bank = sum(l['debit'] - l['credit'] for l in doc['lines'] if l['account'] == '1920')
            assert bank == (doc['amount'] if doc['kind'] == 'receipt' else -doc['amount']), 'bank mismatch'
    for source, amount in paid.items():
        credits = sum(d['gross'] for d in documents.values() if d['kind'] == 'credit' and d['source'] == source)
        assert amount == documents[source]['gross'] + credits, 'payment settlement'
    result = {'opening': dict(opening), 'closing': dict(closing), 'vat_base': dict(bases), 'vat': dict(taxes), 'vat_payable': taxes.get('3', 0) - taxes.get('1', 0)}
    assert result == data['expected'], 'independent expected totals'
    return result
