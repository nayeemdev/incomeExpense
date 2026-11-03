import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import 'transaction_tile.dart';

const _typeNames = {'withdrawal': 'Expense', 'deposit': 'Income', 'transfer': 'Transfer'};

class TransactionScreen extends ConsumerWidget {
  const TransactionScreen({super.key, required this.groupId});

  final int groupId;

  Future<void> _delete(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this transaction?'),
        content: const Text('It moves to the trash. You can restore it from the web app.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Palette.of(context).negative, minimumSize: const Size(0, 40)),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;
    try {
      await ref.read(apiProvider).delete(ledgerPath(ref.read(currentLedgerProvider), 'transactions/$groupId'));
      refreshLedgerData(ref);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Transaction deleted')));
        context.pop();
      }
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
        leading: const BackButton(),
        actions: [
          if (canEdit && group.hasValue) ...[
            IconButton(
              tooltip: 'Edit',
              icon: const Icon(LucideIcons.pencil, size: 20),
              onPressed: () => context.push('/transactions/$groupId/edit'),
            ),
            IconButton(tooltip: 'Delete', icon: const Icon(LucideIcons.trash2, size: 20), onPressed: () => _delete(context, ref)),
            const SizedBox(width: 4),
          ],
        ],
      ),
      body: AsyncView(
        value: group,
        onRetry: () => ref.invalidate(transactionGroupProvider(groupId)),
        builder: (group) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
          children: [
            if (group.title != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 0, 4, 12),
                child: Text(group.title!, style: Theme.of(context).textTheme.headlineSmall),
              ),
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
    final text = Theme.of(context).textTheme;
    final rows = <(IconData, String, String)>[
      (LucideIcons.calendar, 'Date', formatDate(transaction.date)),
      (LucideIcons.arrowUpRight, 'From', accountName(transaction.source)),
      (LucideIcons.arrowDownLeft, 'To', accountName(transaction.destination)),
      if (transaction.category != null) (LucideIcons.folder, 'Category', transaction.category!.name),
      if (transaction.budget != null) (LucideIcons.chartPie, 'Budget', transaction.budget!.name),
      if (transaction.bill != null) (LucideIcons.receipt, 'Bill', transaction.bill!.name),
      if (transaction.tags.isNotEmpty) (LucideIcons.tag, 'Tags', transaction.tags.join(', ')),
      if (transaction.notes != null) (LucideIcons.notebookPen, 'Notes', transaction.notes!),
    ];

    return SurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              children: [
                IconTile(transactionIcon(transaction.type), tone: transactionTone(transaction.type), size: 48, round: true),
                const SizedBox(height: 12),
                Text(transaction.description, style: text.titleLarge, textAlign: TextAlign.center),
                const SizedBox(height: 4),
                MoneyText(
                  signedAmount(transaction),
                  transaction.currency.code,
                  decimals: transaction.currency.decimals,
                  tone: moneyTone(transaction.type),
                  style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w700, letterSpacing: -0.6),
                ),
                const SizedBox(height: 4),
                Text(_typeNames[transaction.type] ?? sentenceCase(transaction.type), style: text.bodySmall?.copyWith(color: palette.muted)),
              ],
            ),
          ),
          const Divider(),
          for (final (index, (icon, label, value)) in rows.indexed) ...[
            if (index > 0) const Divider(indent: 48),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(icon, size: 18, color: palette.muted),
                  const SizedBox(width: 14),
                  SizedBox(
                    width: 80,
                    child: Text(label, style: text.bodyMedium?.copyWith(color: palette.muted)),
                  ),
                  Expanded(
                    child: Text(
                      value,
                      style: text.bodyMedium?.copyWith(fontWeight: FontWeight.w500),
                      textAlign: TextAlign.right,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
