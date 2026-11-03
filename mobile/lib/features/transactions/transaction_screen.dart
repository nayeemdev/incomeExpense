import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import 'transaction_tile.dart';

class TransactionScreen extends ConsumerWidget {
  const TransactionScreen({super.key, required this.groupId});

  final int groupId;

  Future<void> _delete(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this transaction?'),
        content: const Text('It is moved to the trash, and you can restore it from the web app.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;
    try {
      await ref.read(apiProvider).delete(ledgerPath(ref.read(currentLedgerProvider), 'transactions/$groupId'));
      refreshLedgerData(ref);
      if (context.mounted) context.pop();
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final group = ref.watch(transactionGroupProvider(groupId));
    final canEdit = ref.watch(currentLedgerProvider).canEdit;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Transaction'),
        actions: [
          if (canEdit && group.hasValue) ...[
            IconButton(
              tooltip: 'Edit',
              icon: const Icon(Icons.edit_outlined),
              onPressed: () => context.push('/transactions/$groupId/edit'),
            ),
            IconButton(tooltip: 'Delete', icon: const Icon(Icons.delete_outline), onPressed: () => _delete(context, ref)),
          ],
        ],
      ),
      body: AsyncView(
        value: group,
        onRetry: () => ref.invalidate(transactionGroupProvider(groupId)),
        builder: (group) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (group.title != null) ...[
              Text(group.title!, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 12),
            ],
            for (final split in group.splits) ...[_SplitDetail(split), const SizedBox(height: 12)],
          ],
        ),
      ),
    );
  }
}

class _SplitDetail extends StatelessWidget {
  const _SplitDetail(this.transaction);

  final Transaction transaction;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final rows = <(String, String)>[
      ('Date', formatDate(transaction.date)),
      ('From', transaction.source.name),
      ('To', transaction.destination.name),
      if (transaction.category != null) ('Category', transaction.category!.name),
      if (transaction.budget != null) ('Budget', transaction.budget!.name),
      if (transaction.bill != null) ('Bill', transaction.bill!.name),
      if (transaction.tags.isNotEmpty) ('Tags', transaction.tags.join(', ')),
      if (transaction.notes != null) ('Notes', transaction.notes!),
    ];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(sentenceCase(transaction.type == 'withdrawal' ? 'expense' : transaction.type == 'deposit' ? 'income' : transaction.type),
                style: TextStyle(color: palette.muted)),
            const SizedBox(height: 4),
            Text(transaction.description, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              formatMoney(signedAmount(transaction), transaction.currency.code, decimals: transaction.currency.decimals),
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: amountColor(context, transaction.type),
                  ),
            ),
            const SizedBox(height: 12),
            for (final (label, value) in rows)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(width: 96, child: Text(label, style: TextStyle(color: palette.muted))),
                    Expanded(child: Text(value)),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
