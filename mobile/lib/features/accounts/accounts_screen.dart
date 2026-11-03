import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import '../transactions/transaction_list.dart';

const _types = {'asset': 'Assets', 'liability': 'Liabilities', 'expense': 'Expense', 'revenue': 'Revenue'};

const _descriptions = {
  'asset': 'Bank accounts, cards and cash you hold. Their balances add up to your net worth.',
  'liability': 'Loans and debts in both directions: money you owe, and money owed to you.',
  'expense': 'Shops, services and people you pay. Each is created the first time you pay them.',
  'revenue': 'Employers, clients and people who pay you. Each is created the first time they pay you.',
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
  _ when account.type == 'expense' => LucideIcons.store,
  _ when account.type == 'revenue' => LucideIcons.briefcase,
  _ => LucideIcons.landmark,
};

String accountColor(Account account) => switch (account.role) {
  'savings' => 'emerald',
  'cash_wallet' => 'amber',
  'credit_card' => 'violet',
  _ when account.type == 'liability' => 'orange',
  _ when account.type == 'expense' || account.type == 'revenue' => 'slate',
  _ => 'blue',
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
    final palette = Palette.of(context);
    final accounts = ref.watch(accountsProvider(_type));

    return Scaffold(
      appBar: AppBar(title: const Text('Accounts')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.refresh(accountsProvider(_type).future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
              child: Segmented<String>(options: _types, value: _type, onChanged: (type) => setState(() => _type = type)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
              child: Text(_descriptions[_type]!, style: TextStyle(color: palette.muted, fontSize: 13.5)),
            ),
            AsyncView(
              value: accounts,
              onRetry: () => ref.invalidate(accountsProvider(_type)),
              builder: (accounts) => accounts.isEmpty
                  ? EmptyState(
                      icon: LucideIcons.wallet,
                      title: 'Nothing here yet',
                      message: _type == 'expense' || _type == 'revenue'
                          ? 'These are created for you as you record transactions.'
                          : 'Add accounts from the web app.',
                    )
                  : Column(children: [for (final account in accounts) AccountTile(account)]),
            ),
          ],
        ),
      ),
    );
  }
}

class AccountTile extends ConsumerWidget {
  const AccountTile(this.account, {super.key});

  final Account account;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final currency = account.currency ?? ref.watch(currentLedgerProvider).currency;
    return ListRow(
      onTap: () => context.push('/accounts/${account.id}', extra: account),
      leading: CategoryIcon(glyph: accountIcon(account), color: accountColor(account)),
      title: account.name,
      subtitle: account.active ? roleLabel(account) : 'Inactive',
      trailing: account.balance == null
          ? Icon(LucideIcons.chevronRight, size: 18, color: palette.subtle)
          : MoneyText(account.balance!, currency.code, decimals: currency.decimals),
    );
  }
}

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key, required this.accountId, this.initial});

  final int accountId;
  final Account? initial;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final account = ref.watch(accountProvider(accountId)).value ?? initial;
    final currency = account?.currency ?? ref.watch(currentLedgerProvider).currency;

    return Scaffold(
      appBar: AppBar(title: Text(account?.name ?? 'Account')),
      body: TransactionList(
        filter: (query: '', type: null, accountId: accountId),
        header: [
          if (account?.balance case final balance?)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
              child: Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: palette.primary,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [BoxShadow(color: palette.accent.withValues(alpha: 0.3), blurRadius: 20, offset: const Offset(0, 10))],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(accountIcon(account!), color: Colors.white, size: 22),
                        const SizedBox(width: 8),
                        Text(
                          roleLabel(account) ?? 'Account',
                          style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    const Text('Balance', style: TextStyle(color: Colors.white70, fontSize: 13)),
                    const SizedBox(height: 2),
                    FittedBox(
                      child: Text(
                        formatMoney(balance, currency.code, decimals: currency.decimals),
                        style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w700, letterSpacing: -1),
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
