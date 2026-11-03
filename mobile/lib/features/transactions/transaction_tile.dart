import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';

IconData transactionIcon(String type) => switch (type) {
  'withdrawal' => LucideIcons.arrowUpRight,
  'deposit' => LucideIcons.arrowDownLeft,
  'transfer' => LucideIcons.arrowLeftRight,
  _ => LucideIcons.scale,
};

Tone transactionTone(String type) => switch (type) {
  'withdrawal' => Tone.negative,
  'deposit' => Tone.positive,
  _ => Tone.neutral,
};

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

class TransactionTile extends StatelessWidget {
  const TransactionTile(this.transaction, {super.key});

  final Transaction transaction;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final subtitle = [
      '${accountName(transaction.source)} → ${accountName(transaction.destination)}',
      ?transaction.category?.name,
    ].join(' · ');

    return InkWell(
      onTap: () => context.push('/transactions/${transaction.groupId}'),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        child: Row(
          children: [
            IconTile(transactionIcon(transaction.type), tone: transactionTone(transaction.type), size: 34, round: true),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(transaction.description, style: text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: text.bodySmall?.copyWith(color: palette.muted),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            MoneyText(
              signedAmount(transaction),
              transaction.currency.code,
              decimals: transaction.currency.decimals,
              tone: moneyTone(transaction.type),
            ),
          ],
        ),
      ),
    );
  }
}

/// Transactions in one surface card per day, under a small uppercase date heading.
List<Widget> groupedByDay(BuildContext context, List<Transaction> transactions) {
  final days = <DateTime, List<Transaction>>{};
  for (final transaction in transactions) {
    days.putIfAbsent(transaction.date, () => []).add(transaction);
  }
  final style = Theme.of(context).textTheme.labelSmall?.copyWith(color: Palette.of(context).muted);
  return [
    for (final MapEntry(key: day, value: items) in days.entries) ...[
      Padding(
        padding: const EdgeInsets.fromLTRB(20, 14, 20, 6),
        child: Text(formatDay(day).toUpperCase(), style: style),
      ),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: SurfaceCard(
          child: Column(
            children: [
              for (final (index, transaction) in items.indexed) ...[if (index > 0) const Divider(indent: 60), TransactionTile(transaction)],
            ],
          ),
        ),
      ),
    ],
  ];
}
