import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../app/preferences.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/widgets.dart';
import '../auth/lock.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import '../notifications/background_alerts.dart';
import '../notifications/ntfy_settings.dart';

const _themeLabels = {ThemeMode.system: 'System', ThemeMode.light: 'Light', ThemeMode.dark: 'Dark'};

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  Future<void> _signOut(BuildContext context, WidgetRef ref) async {
    final confirmed = await showConfirm(
      context,
      icon: LucideIcons.logOut,
      title: 'Confirm logout',
      message: 'Are you sure you want to log out? You will need your password to sign in again.',
      confirm: 'Logout',
    );
    if (confirmed) await ref.read(sessionProvider.notifier).signOut();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final user = ref.watch(userProvider).value;
    final session = ref.watch(sessionProvider).value;
    final ledgers = ref.watch(ledgersProvider).value ?? const [];
    final current = ref.watch(currentLedgerProvider);
    final lock = ref.watch(lockEnabledProvider).value ?? false;
    final theme = ref.watch(themeModeProvider).value ?? ThemeMode.system;
    final ntfy = ref.watch(ntfySettingsProvider);
    final backgroundAlerts = ref.watch(backgroundAlertsProvider).value ?? false;
    final chevron = Icon(LucideIcons.chevronRight, size: 18, color: palette.subtle);
    final initials = (user?.name ?? '?')
        .trim()
        .split(RegExp(r'\s+'))
        .take(2)
        .map((part) => part.isEmpty ? '' : part[0].toUpperCase())
        .join();

    return Scaffold(
      appBar: AppBar(title: const Text('Settings'), automaticallyImplyLeading: false),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 120),
        children: [
          SurfaceCard(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 56,
                  height: 56,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(color: palette.primary, shape: BoxShape.circle),
                  child: Text(
                    initials,
                    style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user?.name ?? '', style: text.titleMedium),
                      Text(user?.email ?? '', style: text.bodySmall?.copyWith(color: palette.muted)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const _Heading('General'),
          _Group(
            children: [
              if (ledgers.length > 1)
                ListRow(
                  leading: const CategoryIcon(glyph: LucideIcons.bookOpen, color: 'indigo', size: 40),
                  title: 'Ledger',
                  subtitle: current.name,
                  trailing: chevron,
                  onTap: () => showAppSheet<void>(
                    context,
                    title: 'Switch ledger',
                    builder: (context) => ListView(
                      shrinkWrap: true,
                      padding: const EdgeInsets.only(bottom: 20),
                      children: [
                        for (final ledger in ledgers)
                          ListRow(
                            leading: const CategoryIcon(glyph: LucideIcons.bookOpen, color: 'indigo', size: 40),
                            title: ledger.name,
                            subtitle: ledger.currency.code,
                            trailing: ledger.id == current.id ? Icon(LucideIcons.circleCheck, color: palette.primary) : null,
                            onTap: () {
                              ref.read(ledgerProvider.notifier).select(ledger);
                              Navigator.pop(context);
                            },
                          ),
                      ],
                    ),
                  ),
                ),
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.wallet, color: 'blue', size: 40),
                title: 'Accounts',
                trailing: chevron,
                onTap: () => context.push('/accounts'),
              ),
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.layoutGrid, color: 'violet', size: 40),
                title: 'Categories',
                trailing: chevron,
                onTap: () => context.push('/categories'),
              ),
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.chartPie, color: 'emerald', size: 40),
                title: 'Budgets & bills',
                trailing: chevron,
                onTap: () => context.push('/planning'),
              ),
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.trendingUp, color: 'sky', size: 40),
                title: 'Investments',
                trailing: chevron,
                onTap: () => context.push('/investments'),
              ),
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.handCoins, color: 'amber', size: 40),
                title: 'Zakat',
                trailing: chevron,
                onTap: () => context.push('/zakat'),
              ),
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.bell, color: 'amber', size: 40),
                title: 'Notifications',
                trailing: chevron,
                onTap: () => context.push('/notifications'),
              ),
            ],
          ),
          const _Heading('Alerts'),
          _Group(
            children: [
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.bellRing, color: 'rose', size: 40),
                title: 'Alerts on this phone',
                subtitle: 'Checks in the background',
                trailing: Switch.adaptive(
                  value: backgroundAlerts,
                  activeTrackColor: palette.primary,
                  onChanged: (value) async {
                    final changed = await ref.read(backgroundAlertsProvider.notifier).set(value);
                    if (!changed && context.mounted) {
                      showToast(context, 'Notifications are turned off for LedgerFly. Allow them in your phone settings.');
                    }
                  },
                ),
              ),
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.smartphone, color: 'orange', size: 40),
                title: 'Phone alerts with ntfy',
                subtitle: ntfy.value?.topic == null ? 'Off' : 'On · ${ntfy.value!.topic}',
                trailing: chevron,
                onTap: ntfy.value == null
                    ? null
                    : () => showAppSheet<void>(
                        context,
                        title: 'Phone alerts',
                        builder: (_) => NtfySheet(settings: ntfy.value!),
                      ),
              ),
            ],
          ),
          const _Heading('Preferences'),
          _Group(
            children: [
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.sunMoon, color: 'sky', size: 40),
                title: 'Change theme',
                subtitle: _themeLabels[theme],
                trailing: chevron,
                onTap: () => showAppSheet<void>(context, title: 'Theme', builder: (_) => const _ThemePicker()),
              ),
            ],
          ),
          const _Heading('Security'),
          _Group(
            children: [
              ListRow(
                leading: const CategoryIcon(glyph: LucideIcons.scanFace, color: 'teal', size: 40),
                title: 'Lock with Face ID',
                subtitle: 'Ask when the app opens',
                trailing: Switch.adaptive(
                  value: lock,
                  activeTrackColor: palette.primary,
                  onChanged: (value) async {
                    final changed = await ref.read(lockEnabledProvider.notifier).set(value);
                    if (!changed && context.mounted) showToast(context, 'Could not confirm it is you, so the lock stays off.');
                  },
                ),
              ),
              if (session != null)
                ListRow(
                  leading: const CategoryIcon(glyph: LucideIcons.server, color: 'slate', size: 40),
                  title: 'Server',
                  subtitle: session.serverUrl,
                ),
            ],
          ),
          const SizedBox(height: 24),
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: palette.negativeTint, foregroundColor: palette.negative),
            onPressed: () => _signOut(context, ref),
            icon: const Icon(LucideIcons.logOut, size: 18),
            label: const Text('Logout'),
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
      padding: const EdgeInsets.fromLTRB(4, 24, 4, 8),
      child: Text(
        text,
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Palette.of(context).muted),
      ),
    );
  }
}

class _Group extends StatelessWidget {
  const _Group({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return SurfaceCard(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(children: children),
    );
  }
}

class _ThemePicker extends ConsumerWidget {
  const _ThemePicker();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final current = ref.watch(themeModeProvider).value ?? ThemeMode.system;
    final previews = {
      ThemeMode.light: (Colors.white, const Color(0xFFEEF0F5)),
      ThemeMode.dark: (const Color(0xFF0B0D14), const Color(0xFF1E2230)),
      ThemeMode.system: (palette.background, palette.mutedBg),
    };

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
      child: Row(
        children: [
          for (final mode in [ThemeMode.light, ThemeMode.dark, ThemeMode.system])
            Expanded(
              child: GestureDetector(
                onTap: () => ref.read(themeModeProvider.notifier).set(mode),
                child: Column(
                  children: [
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      height: 120,
                      margin: const EdgeInsets.symmetric(horizontal: 6),
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: previews[mode]!.$1,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: mode == current ? palette.primary : palette.border, width: mode == current ? 2.5 : 1),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            height: 10,
                            width: 40,
                            decoration: BoxDecoration(color: palette.primary, borderRadius: BorderRadius.circular(4)),
                          ),
                          const SizedBox(height: 8),
                          for (var i = 0; i < 3; i++)
                            Container(
                              height: 12,
                              margin: const EdgeInsets.only(bottom: 6),
                              decoration: BoxDecoration(color: previews[mode]!.$2, borderRadius: BorderRadius.circular(4)),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _themeLabels[mode]!,
                      style: TextStyle(
                        fontWeight: mode == current ? FontWeight.w600 : FontWeight.w500,
                        color: mode == current ? palette.primary : palette.muted,
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
