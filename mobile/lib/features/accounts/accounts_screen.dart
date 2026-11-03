import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import '../transactions/transaction_list.dart';

const _tabs = {
  'asset': 'Assets',
  'liability': 'Liabilities',
  'expense': 'Expenses',
  'revenue': 'Revenue',
};

class AccountsScreen extends StatelessWidget {
  const AccountsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: _tabs.length,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Accounts'),
          bottom: TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [for (final label in _tabs.values) Tab(text: label)],
          ),
        ),
        body: TabBarView(children: [for (final type in _tabs.keys) _AccountList(type)]),
      ),
    );
  }
}

class _AccountList extends ConsumerWidget {
  const _AccountList(this.type);

  final String type;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final accounts = ref.watch(accountsProvider(type));
    return RefreshIndicator(
      onRefresh: () => ref.refresh(accountsProvider(type).future),
      child: AsyncView(
        value: accounts,
        onRetry: () => ref.invalidate(accountsProvider(type)),
        builder: (accounts) => accounts.isEmpty
            ? ListView(children: [
                MessageView(
                  icon: Icons.account_balance_outlined,
                  title: 'No accounts yet',
                  message: type == 'expense' || type == 'revenue'
                      ? 'These are created for you when you record transactions.'
                      : 'Add accounts from the web app.',
                ),
              ])
            : ListView.separated(
                padding: const EdgeInsets.symmetric(vertical: 8),
                itemCount: accounts.length,
                separatorBuilder: (_, _) => const Divider(indent: 16, endIndent: 16),
                itemBuilder: (context, index) => AccountTile(accounts[index]),
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
    final currency = account.currency ?? ref.watch(currentLedgerProvider).currency;
    final balance = account.balance;
    final negative = balance != null && balance.startsWith('-');

    return ListTile(
      onTap: () => context.push('/accounts/${account.id}', extra: account),
      title: Text(account.name),
      subtitle: account.role == null ? null : Text(sentenceCase(account.role!)),
      trailing: balance == null
          ? const Icon(Icons.chevron_right)
          : Text(
              formatMoney(balance, currency.code, decimals: currency.decimals),
              style: TextStyle(fontWeight: FontWeight.w600, color: negative ? Palette.of(context).expense : null),
            ),
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
      appBar: AppBar(title: Text(account?.name ?? '')),
      body: TransactionList(
        filter: (query: '', type: null, accountId: accountId),
        header: account?.balance == null
            ? null
            : Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Balance', style: TextStyle(color: Palette.of(context).muted)),
                    Text(
                      formatMoney(account!.balance!, currency.code, decimals: currency.decimals),
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}
