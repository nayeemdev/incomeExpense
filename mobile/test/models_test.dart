import 'package:flutter_test/flutter_test.dart';
import 'package:ledgerfly/api/models.dart';
import 'package:ledgerfly/features/transactions/outbox.dart';
import 'package:ledgerfly/shared/format.dart';

void main() {
  const currency = {'id': 1, 'code': 'USD', 'name': 'US Dollar', 'decimal_places': 2};

  test('parses a transaction from the API shape', () {
    final transaction = Transaction.fromJson({
      'id': 5,
      'group_id': 4,
      'group_title': null,
      'type': 'withdrawal',
      'date': '2026-11-03',
      'description': 'Rickshaw',
      'source': {'id': 1, 'name': 'Wallet', 'type': 'asset'},
      'destination': {'id': 9, 'name': 'Rickshaw', 'type': 'expense'},
      'currency': currency,
      'amount': '60.00',
      'category': {'id': 2, 'name': 'Transport'},
      'tags': [
        {'id': 1, 'name': 'commute'},
      ],
      'notes': null,
    });

    expect(transaction.destination.name, 'Rickshaw');
    expect(transaction.category?.name, 'Transport');
    expect(transaction.tags, ['commute']);
    expect(transaction.date, DateTime(2026, 11, 3));
  });

  test('a bill is paid once every expected date has a payment', () {
    Bill bill(int payments) => Bill.fromJson({
      'id': 1,
      'name': 'Rent',
      'currency': currency,
      'amount_min': '1200.00',
      'amount_max': '1200.00',
      'repeat_freq': 'monthly',
      'next_expected_date': '2026-11-01',
      'expected_dates': ['2026-11-01'],
      'payments': List.filled(payments, {'transaction_id': 1}),
    });

    expect(bill(0).isDue, isTrue);
    expect(bill(1).isPaid, isTrue);
  });

  test('formats money with sign and currency', () {
    expect(formatMoney('-60', 'USD'), '-\$60.00');
    expect(formatMoney('1250.5', 'USD', signed: true), '+\$1,250.50');
    expect(negate('60.00'), '-60.00');
    expect(negate('-60.00'), '60.00');
  });

  test('an offline entry survives being written and read back', () {
    const pending = Pending(ledgerId: 3, body: {'transactions': []}, receipt: '/tmp/receipt.jpg');
    final restored = Pending.fromJson(pending.toJson());

    expect(restored.ledgerId, 3);
    expect(restored.body, {'transactions': []});
    expect(restored.receipt, '/tmp/receipt.jpg');
  });
}
