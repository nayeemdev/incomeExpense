import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';

/// Signed amount as seen from the ledger: money leaving is negative, money arriving positive.
String signedAmount(Transaction transaction) => transaction.type == 'withdrawal' ? negate(transaction.amount) : transaction.amount;

MoneyTone moneyTone(String type) => switch (type) {
  'withdrawal' => MoneyTone.negative,
  'deposit' => MoneyTone.positive,
  _ => MoneyTone.none,
};

String accountName(AccountRef account) => switch (account.type) {
  'initial_balance' => 'Starting balance',
  'reconciliation' => 'Reconciliation',
  _ => account.name,
};

String counterpartyOf(Transaction transaction) => switch (transaction.type) {
  'withdrawal' => accountName(transaction.destination),
  'deposit' => accountName(transaction.source),
  _ => '${accountName(transaction.source)} → ${accountName(transaction.destination)}',
};

/// The icon tile for a transaction: its category's, or one that says what kind of movement it is.
Widget transactionIcon(Transaction transaction, {double size = 46}) {
  final category = transaction.category;
  if (category != null) return CategoryIcon(icon: category.icon, color: category.color, size: size);
  return switch (transaction.type) {
    'transfer' => CategoryIcon(glyph: LucideIcons.arrowLeftRight, color: 'indigo', size: size),
    'deposit' => CategoryIcon(glyph: LucideIcons.arrowDownLeft, color: 'emerald', size: size),
    'withdrawal' => CategoryIcon(glyph: LucideIcons.arrowUpRight, color: 'slate', size: size),
    _ => CategoryIcon(glyph: LucideIcons.scale, color: 'slate', size: size),
  };
}

class TransactionTile extends StatelessWidget {
  const TransactionTile(this.transaction, {super.key, this.showDate = true});

  final Transaction transaction;
  final bool showDate;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return ListRow(
      onTap: () => context.push('/transactions/${transaction.groupId}'),
      leading: transactionIcon(transaction),
      title: transaction.description,
      subtitle: transaction.category?.name ?? counterpartyOf(transaction),
      trailing: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          MoneyText(
            signedAmount(transaction),
            transaction.currency.code,
            decimals: transaction.currency.decimals,
            tone: moneyTone(transaction.type),
          ),
          if (showDate) ...[
            const SizedBox(height: 2),
            Text(DateFormat('d MMM').format(transaction.date), style: TextStyle(fontSize: 12, color: palette.subtle)),
          ],
        ],
      ),
    );
  }
}

/// Transactions under a small heading per day.
List<Widget> groupedByDay(BuildContext context, List<Transaction> transactions) {
  final days = <DateTime, List<Transaction>>{};
  for (final transaction in transactions) {
    days.putIfAbsent(transaction.date, () => []).add(transaction);
  }
  final palette = Palette.of(context);
  return [
    for (final MapEntry(key: day, value: items) in days.entries) ...[
      Padding(
        padding: const EdgeInsets.fromLTRB(20, 18, 20, 4),
        child: Text(
          formatDay(day),
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: palette.muted),
        ),
      ),
      for (final transaction in items) TransactionTile(transaction, showDate: false),
    ],
  ];
}
