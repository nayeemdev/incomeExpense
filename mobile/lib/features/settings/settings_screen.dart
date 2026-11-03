import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../app/theme.dart';
import '../../shared/widgets.dart';
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
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;

    Widget row({
      required IconData icon,
      required String title,
      String? subtitle,
      Widget? trailing,
      VoidCallback? onTap,
      Tone tone = Tone.neutral,
    }) => InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            IconTile(icon, tone: tone, size: 34),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: text.titleSmall?.copyWith(color: tone == Tone.negative ? palette.negative : null)),
                  if (subtitle != null) Text(subtitle, style: text.bodySmall?.copyWith(color: palette.muted)),
                ],
              ),
            ),
            ?trailing,
          ],
        ),
      ),
    );

    return Scaffold(
      appBar: AppBar(leading: const BackButton()),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          const PageHeader(title: 'Settings', description: 'Security and the ledger you are working in.'),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              children: [
                if (ledgers.length > 1) ...[
                  SectionCard(
                    title: 'Ledger',
                    child: Column(
                      children: [
                        for (final (index, ledger) in ledgers.indexed) ...[
                          if (index > 0) const Divider(indent: 64),
                          row(
                            icon: LucideIcons.bookOpen,
                            title: ledger.name,
                            subtitle: ledger.currency.code,
                            tone: ledger.id == current.id ? Tone.brand : Tone.neutral,
                            trailing: ledger.id == current.id
                                ? Icon(LucideIcons.check, size: 18, color: Theme.of(context).colorScheme.primary)
                                : null,
                            onTap: () => ref.read(ledgerProvider.notifier).select(ledger),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
                SectionCard(
                  title: 'Security',
                  child: row(
                    icon: LucideIcons.scanFace,
                    title: 'Lock with Face ID',
                    subtitle: 'Ask for Face ID, Touch ID or your passcode when you open the app.',
                    trailing: Switch.adaptive(
                      value: lock,
                      onChanged: (value) async {
                        final changed = await ref.read(lockEnabledProvider.notifier).set(value);
                        if (!changed && context.mounted) {
                          ScaffoldMessenger.of(context)
                              .showSnackBar(const SnackBar(content: Text('Could not confirm it is you, so the lock stays off.')));
                        }
                      },
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                SectionCard(
                  title: 'Account',
                  child: Column(
                    children: [
                      if (session != null) row(icon: LucideIcons.server, title: 'Server', subtitle: session.serverUrl),
                      const Divider(indent: 64),
                      row(
                        icon: LucideIcons.logOut,
                        title: 'Sign out',
                        tone: Tone.negative,
                        onTap: () => ref.read(sessionProvider.notifier).signOut(),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
