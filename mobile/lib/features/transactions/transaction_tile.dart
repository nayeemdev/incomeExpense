import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';

IconData transactionIcon(String type) => switch (type) {
      'withdrawal' => Icons.arrow_upward,
      'deposit' => Icons.arrow_downward,
      'transfer' => Icons.swap_horiz,
      _ => Icons.tune,
    };

/// Signed amount as seen from the ledger: money leaving is negative, money arriving positive.
String signedAmount(Transaction transaction) => transaction.type == 'withdrawal' ? negate(transaction.amount) : transaction.amount;

Color amountColor(BuildContext context, String type) {
  final palette = Palette.of(context);
  return switch (type) {
    'withdrawal' => palette.expense,
    'deposit' => palette.income,
    _ => Theme.of(context).colorScheme.onSurface,
  };
}

class TransactionTile extends StatelessWidget {
  const TransactionTile(this.transaction, {super.key});

  final Transaction transaction;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final counterparty = switch (transaction.type) {
      'withdrawal' => transaction.destination.name,
      'deposit' => transaction.source.name,
      _ => '${transaction.source.name} → ${transaction.destination.name}',
    };
    final subtitle = [counterparty, ?transaction.category?.name].join(' · ');

    return ListTile(
      onTap: () => context.push('/transactions/${transaction.groupId}'),
      leading: CircleAvatar(
        radius: 18,
        backgroundColor: palette.surface,
        child: Icon(transactionIcon(transaction.type), size: 18, color: amountColor(context, transaction.type)),
      ),
      title: Text(transaction.description, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: Text(
        formatMoney(signedAmount(transaction), transaction.currency.code, decimals: transaction.currency.decimals),
        style: TextStyle(fontWeight: FontWeight.w600, color: amountColor(context, transaction.type)),
      ),
    );
  }
}

class DayHeader extends StatelessWidget {
  const DayHeader(this.date, {super.key});

  final DateTime date;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
      child: Text(
        formatDay(date).toUpperCase(),
        style: Theme.of(context).textTheme.labelSmall?.copyWith(color: Palette.of(context).muted, letterSpacing: 0.6),
      ),
    );
  }
}

/// Lays out transactions with a header before each new day.
List<Widget> groupedByDay(List<Transaction> transactions) {
  final widgets = <Widget>[];
  DateTime? day;
  for (final transaction in transactions) {
    if (day != transaction.date) {
      day = transaction.date;
      widgets.add(DayHeader(transaction.date));
    }
    widgets.add(TransactionTile(transaction));
  }
  return widgets;
}
