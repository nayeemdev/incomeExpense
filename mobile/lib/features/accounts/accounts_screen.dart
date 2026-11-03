import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import '../transactions/transaction_list.dart';

const _types = {'asset': 'Assets', 'liability': 'Liabilities', 'expense': 'Expenses', 'revenue': 'Revenue'};

const _descriptions = {
  'asset': 'Bank accounts, cash and cards you hold.',
  'liability': 'Loans and debts. Covers money you owe and money owed to you.',
  'expense': 'Shops and people you pay. Created as you record expenses.',
  'revenue': 'Employers and others who pay you.',
};

const _roleLabels = {
  'default': 'Checking',
  'shared': 'Shared',
  'savings': 'Savings',
  'cash_wallet': 'Cash wallet',
  'credit_card': 'Credit card',
};

String? roleLabel(Account account) => account.role == null ? null : _roleLabels[account.role] ?? sentenceCase(account.role!);

IconData accountIcon(Account account) => switch (account.role) {
  'savings' => LucideIcons.piggyBank,
  'cash_wallet' => LucideIcons.wallet,
  'credit_card' => LucideIcons.creditCard,
  _ when account.type == 'liability' => LucideIcons.handCoins,
  _ when account.type == 'expense' => LucideIcons.shoppingBag,
  _ when account.type == 'revenue' => LucideIcons.briefcase,
  _ => LucideIcons.landmark,
};

class AccountsScreen extends ConsumerStatefulWidget {
  const AccountsScreen({super.key});

  @override
  ConsumerState<AccountsScreen> createState() => _AccountsScreenState();
}

class _AccountsScreenState extends ConsumerState<AccountsScreen> {
  String _type = 'asset';

  @override
  Widget build(BuildContext context) {
    final accounts = ref.watch(accountsProvider(_type));

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator.adaptive(
          onRefresh: () => ref.refresh(accountsProvider(_type).future),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 110),
            children: [
              PageHeader(title: 'Accounts', description: _descriptions[_type]),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: Segmented<String>(options: _types, value: _type, onChanged: (type) => setState(() => _type = type)),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: AsyncView(
                  value: accounts,
                  onRetry: () => ref.invalidate(accountsProvider(_type)),
                  loading: const SurfaceCard(child: Column(children: [SkeletonRow(), SkeletonRow(), SkeletonRow(), SkeletonRow()])),
                  builder: (accounts) => SurfaceCard(
                    child: accounts.isEmpty
                        ? EmptyState(
                            icon: LucideIcons.wallet,
                            title: 'No accounts yet',
                            message: _type == 'expense' || _type == 'revenue'
                                ? 'These are created for you when you record transactions.'
                                : 'Add accounts from the web app.',
                          )
                        : Column(
                            children: [
                              for (final (index, account) in accounts.indexed) ...[
                                if (index > 0) const Divider(indent: 64),
                                AccountTile(account, showIcon: true),
                              ],
                            ],
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class AccountTile extends ConsumerWidget {
  const AccountTile(this.account, {super.key, this.showIcon = false});

  final Account account;
  final bool showIcon;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final currency = account.currency ?? ref.watch(currentLedgerProvider).currency;
    final role = roleLabel(account);

    return InkWell(
      onTap: () => context.push('/accounts/${account.id}', extra: account),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            if (showIcon) ...[IconTile(accountIcon(account), size: 34), const SizedBox(width: 14)],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(account.name, style: text.titleSmall, overflow: TextOverflow.ellipsis),
                      ),
                      if (!account.active) ...[const SizedBox(width: 6), _Badge('Inactive', palette)],
                    ],
                  ),
                  if (role != null) Text(role, style: text.bodySmall?.copyWith(color: palette.muted)),
                ],
              ),
            ),
            if (account.balance != null)
              MoneyText(account.balance!, currency.code, decimals: currency.decimals)
            else
              Icon(LucideIcons.chevronRight, size: 18, color: palette.muted),
          ],
        ),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge(this.label, this.palette);

  final String label;
  final Palette palette;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
      decoration: BoxDecoration(
        border: Border.all(color: palette.border),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(label, style: TextStyle(fontSize: 11, color: palette.muted)),
    );
  }
}

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key, required this.accountId, this.initial});

  final int accountId;
  final Account? initial;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final account = ref.watch(accountProvider(accountId)).value ?? initial;
    final currency = account?.currency ?? ref.watch(currentLedgerProvider).currency;

    return Scaffold(
      appBar: AppBar(leading: const BackButton()),
      body: TransactionList(
        filter: (query: '', type: null, accountId: accountId),
        header: [
          PageHeader(title: account?.name ?? '', description: account == null ? null : roleLabel(account)),
          if (account?.balance case final balance?)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
              child: StatCard(
                label: 'Balance',
                value: formatMoney(balance, currency.code, decimals: currency.decimals),
                icon: accountIcon(account!),
                tone: balance.startsWith('-') ? Tone.negative : Tone.brand,
              ),
            ),
        ],
      ),
    );
  }
}
