import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import '../receipts/receipts.dart';
import 'transaction_tile.dart';

const _typeNames = {'withdrawal': 'Expense', 'deposit': 'Income', 'transfer': 'Transfer'};

class TransactionScreen extends ConsumerWidget {
  const TransactionScreen({super.key, required this.groupId});

  final String groupId;

  Future<void> _delete(BuildContext context, WidgetRef ref) async {
    final confirmed = await showConfirm(
      context,
      icon: LucideIcons.trash2,
      title: 'Delete transaction',
      message: 'Are you sure you want to delete this transaction? It moves to the trash, and you can restore it from the web app.',
      confirm: 'Delete',
    );
    if (!confirmed || !context.mounted) return;
    try {
      await ref.read(apiProvider).delete(ledgerPath(ref.read(currentLedgerProvider), 'transactions/$groupId'));
      refreshLedgerData(ref.invalidate);
      if (context.mounted) {
        showToast(context, 'Transaction deleted');
        closeScreen(context);
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
        leading: context.canPop() ? null : IconButton(icon: const Icon(LucideIcons.x, size: 22), onPressed: () => closeScreen(context)),
        title: const Text('Transaction details'),
        actions: [
          if (canEdit && group.hasValue)
            TextButton(onPressed: () => context.push('/transactions/$groupId/edit'), child: const Text('Edit')),
          const SizedBox(width: 8),
        ],
      ),
      body: AsyncView(
        value: group,
        onRetry: () => ref.invalidate(transactionGroupProvider(groupId)),
        builder: (group) => Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                children: [
                  if (group.title != null)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(4, 0, 4, 12),
                      child: Text(group.title!, style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
                    ),
                  for (final split in group.splits) ...[_SplitDetail(split), const SizedBox(height: 14)],
                  ReceiptsSection(groupId: group.id),
                ],
              ),
            ),
            if (canEdit)
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                  child: SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(backgroundColor: Palette.of(context).negative),
                      onPressed: () => _delete(context, ref),
                      icon: const Icon(LucideIcons.trash2, size: 18),
                      label: const Text('Delete'),
                    ),
                  ),
                ),
              ),
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
    final rows = <(String, Widget)>[
      ('Type', Text(_typeNames[transaction.type] ?? transaction.type)),
      ('Date', Text(DateFormat('EEE, d MMM y').format(transaction.date))),
      ('From', Text(accountName(transaction.source))),
      ('To', Text(accountName(transaction.destination))),
      if (transaction.category != null)
        (
          'Category',
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              CategoryIcon(icon: transaction.category!.icon, color: transaction.category!.color, size: 24),
              const SizedBox(width: 8),
              Text(transaction.category!.name),
            ],
          ),
        ),
      if (transaction.budget != null) ('Budget', Text(transaction.budget!.name)),
      if (transaction.bill != null) ('Bill', Text(transaction.bill!.name)),
      if (transaction.tags.isNotEmpty) ('Tags', Text(transaction.tags.join(', '))),
      if (transaction.notes != null) ('Notes', Text(transaction.notes!)),
    ];

    return Column(
      children: [
        const SizedBox(height: 8),
        transactionIcon(transaction, size: 64),
        const SizedBox(height: 14),
        Text(transaction.description, style: text.titleLarge, textAlign: TextAlign.center),
        const SizedBox(height: 6),
        BigAmount(
          signedAmount(transaction),
          transaction.currency.code,
          size: 36,
          decimals: transaction.currency.decimals,
          color: switch (transaction.type) {
            'withdrawal' => palette.negative,
            'deposit' => palette.positive,
            _ => null,
          },
        ),
        const SizedBox(height: 20),
        SurfaceCard(
          child: Column(
            children: [
              for (final (index, (label, value)) in rows.indexed) ...[
                if (index > 0) Divider(indent: 16, endIndent: 16, color: palette.border),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: 90,
                        child: Text(label, style: TextStyle(color: palette.muted)),
                      ),
                      Expanded(
                        child: Align(
                          alignment: Alignment.centerRight,
                          child: DefaultTextStyle.merge(
                            style: const TextStyle(fontWeight: FontWeight.w600),
                            textAlign: TextAlign.right,
                            child: value,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
