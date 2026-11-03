import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../auth/lock.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider).value;
    final ledgers = ref.watch(ledgersProvider).value ?? const [];
    final current = ref.watch(currentLedgerProvider);
    final lock = ref.watch(lockEnabledProvider).value ?? false;

    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          if (ledgers.length > 1) ...[
            const _Heading('Ledger'),
            RadioGroup<int>(
              groupValue: current.id,
              onChanged: (id) => ref.read(ledgerProvider.notifier).select(ledgers.firstWhere((ledger) => ledger.id == id)),
              child: Column(
                children: [
                  for (final ledger in ledgers)
                    RadioListTile<int>(value: ledger.id, title: Text(ledger.name), subtitle: Text(ledger.currency.code)),
                ],
              ),
            ),
          ],
          const _Heading('Security'),
          SwitchListTile(
            title: const Text('Lock with Face ID'),
            subtitle: const Text('Ask for Face ID, Touch ID or your passcode when you open the app.'),
            value: lock,
            onChanged: (value) async {
              final changed = await ref.read(lockEnabledProvider.notifier).set(value);
              if (!changed && context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Could not confirm it is you, so the lock stays off.')),
                );
              }
            },
          ),
          const _Heading('Account'),
          if (session != null) ListTile(title: const Text('Server'), subtitle: Text(session.serverUrl)),
          ListTile(
            leading: Icon(Icons.logout, color: Palette.of(context).expense),
            title: Text('Sign out', style: TextStyle(color: Palette.of(context).expense)),
            onTap: () async {
              await ref.read(sessionProvider.notifier).signOut();
            },
          ),
        ],
      ),
    );
  }
}

class _Heading extends StatelessWidget {
  const _Heading(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 4),
      child: Text(text.toUpperCase(), style: Theme.of(context).textTheme.labelSmall?.copyWith(color: Palette.of(context).muted, letterSpacing: 0.6)),
    );
  }
}
