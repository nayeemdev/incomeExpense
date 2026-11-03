import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../api/models.dart';
import '../features/accounts/accounts_screen.dart';
import '../features/analytics/analytics_screen.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/session.dart';
import '../features/categories/categories.dart';
import '../features/goals/goals_screen.dart';
import '../features/home/home_screen.dart';
import '../features/ledger/ledger.dart';
import '../features/ledger/ledger_data.dart';
import '../features/notifications/notifications_screen.dart';
import '../features/planning/planning_screen.dart';
import '../features/settings/settings_screen.dart';
import '../features/transactions/quick_add_screen.dart';
import '../features/transactions/transaction_form_screen.dart';
import '../features/transactions/transaction_screen.dart';
import '../features/transactions/transactions_screen.dart';
import '../shared/widgets.dart';
import 'theme.dart';

final _rootKey = GlobalKey<NavigatorState>();

Page<void> _sheet(GoRouterState state, Widget child) => MaterialPage(key: state.pageKey, fullscreenDialog: true, child: child);

final routerProvider = Provider<GoRouter>((ref) {
  final signedIn = ValueNotifier(ref.read(sessionProvider).value != null);
  ref.listen(sessionProvider, (_, next) => signedIn.value = next.value != null);
  ref.onDispose(signedIn.dispose);

  GoRoute pushed(String path, Widget Function(GoRouterState state) build) =>
      GoRoute(parentNavigatorKey: _rootKey, path: path, builder: (_, state) => build(state));

  return GoRouter(
    navigatorKey: _rootKey,
    refreshListenable: signedIn,
    redirect: (context, state) {
      final atLogin = state.matchedLocation == '/login';
      if (!signedIn.value) return atLogin ? null : '/login';
      return atLogin ? '/' : null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(
        parentNavigatorKey: _rootKey,
        path: '/new',
        pageBuilder: (_, state) => _sheet(state, QuickAddScreen(type: state.uri.queryParameters['type'] ?? 'withdrawal')),
        routes: [
          GoRoute(parentNavigatorKey: _rootKey, path: 'split', pageBuilder: (_, state) => _sheet(state, const TransactionFormScreen())),
        ],
      ),
      pushed('/transactions', (_) => const TransactionsScreen()),
      pushed('/transactions/:id', (state) => TransactionScreen(groupId: int.parse(state.pathParameters['id']!))),
      GoRoute(
        parentNavigatorKey: _rootKey,
        path: '/transactions/:id/edit',
        pageBuilder: (_, state) => _sheet(state, _EditTransaction(groupId: int.parse(state.pathParameters['id']!))),
      ),
      pushed('/accounts', (_) => const AccountsScreen()),
      pushed(
        '/accounts/:id',
        (state) => AccountScreen(accountId: int.parse(state.pathParameters['id']!), initial: state.extra as Account?),
      ),
      pushed('/planning', (_) => const PlanningScreen()),
      pushed('/categories', (_) => const CategoriesScreen()),
      pushed('/notifications', (_) => const NotificationsScreen()),
      pushed('/goals/:id', (state) => GoalScreen(goalId: int.parse(state.pathParameters['id']!), color: state.extra as String? ?? 'sky')),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => _Shell(shell),
        branches: [
          StatefulShellBranch(
            routes: [GoRoute(path: '/', builder: (_, _) => const HomeScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/analytics', builder: (_, _) => const AnalyticsScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/goals', builder: (_, _) => const GoalsScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/settings', builder: (_, _) => const SettingsScreen())],
          ),
        ],
      ),
    ],
  );
});

/// Opens the keypad editor for a single transaction, or the split editor for split ones.
class _EditTransaction extends ConsumerWidget {
  const _EditTransaction({required this.groupId});

  final int groupId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final group = ref.watch(transactionGroupProvider(groupId));
    return switch (group) {
      AsyncValue(hasValue: true, :final value?) when value.splits.length == 1 => QuickAddScreen(group: value),
      AsyncValue(hasValue: true) => TransactionFormScreen(groupId: groupId),
      _ => Scaffold(
        appBar: AppBar(),
        body: AsyncView(value: group, builder: (_) => const SizedBox()),
      ),
    };
  }
}

class _Shell extends ConsumerWidget {
  const _Shell(this.shell);

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ledger = ref.watch(ledgerProvider);

    return switch (ledger) {
      AsyncValue(hasValue: true, value: null) => Scaffold(
        body: Center(
          child: EmptyState(
            icon: LucideIcons.bookOpen,
            title: 'No ledger yet',
            message: 'Create a ledger in the web app, then refresh here.',
            action: OutlinedButton(onPressed: () => ref.invalidate(ledgersProvider), child: const Text('Refresh')),
          ),
        ),
      ),
      AsyncValue(hasValue: true, :final value?) => Scaffold(
        extendBody: true,
        body: shell,
        bottomNavigationBar: _BottomBar(shell: shell, canAdd: value.canEdit),
      ),
      AsyncValue(:final error?) => Scaffold(
        body: Center(
          child: AsyncView(
            value: AsyncError<void>(error, StackTrace.empty),
            onRetry: () => ref.invalidate(ledgersProvider),
            builder: (_) => const SizedBox(),
          ),
        ),
      ),
      _ => const Scaffold(body: Center(child: LogoMark(size: 56))),
    };
  }
}

class _BottomBar extends StatelessWidget {
  const _BottomBar({required this.shell, required this.canAdd});

  final StatefulNavigationShell shell;
  final bool canAdd;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    Widget item(int index, IconData icon, String label) {
      final selected = shell.currentIndex == index;
      return Expanded(
        child: InkResponse(
          onTap: () {
            HapticFeedback.selectionClick();
            shell.goBranch(index, initialLocation: index == shell.currentIndex);
          },
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                decoration: BoxDecoration(
                  color: selected ? palette.primaryTint : Colors.transparent,
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Icon(icon, size: 22, color: selected ? palette.primary : palette.subtle),
              ),
              const SizedBox(height: 3),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                  color: selected ? palette.foreground : palette.subtle,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Container(
      decoration: BoxDecoration(
        color: palette.card,
        border: Border(top: BorderSide(color: palette.border)),
        boxShadow: palette.shadow.isEmpty ? null : const [BoxShadow(color: Color(0x0A0E1726), blurRadius: 20, offset: Offset(0, -4))],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 66,
          child: Row(
            children: [
              item(0, LucideIcons.house, 'Home'),
              item(1, LucideIcons.chartColumn, 'Analytics'),
              SizedBox(
                width: 76,
                child: Center(
                  child: canAdd
                      ? GestureDetector(
                          onTap: () {
                            HapticFeedback.mediumImpact();
                            context.push('/new');
                          },
                          child: Container(
                            width: 56,
                            height: 56,
                            decoration: BoxDecoration(
                              color: palette.primary,
                              shape: BoxShape.circle,
                              boxShadow: [
                                BoxShadow(color: palette.accent.withValues(alpha: 0.4), blurRadius: 16, offset: const Offset(0, 6)),
                              ],
                            ),
                            child: const Icon(LucideIcons.plus, color: Colors.white, size: 28),
                          ),
                        )
                      : null,
                ),
              ),
              item(2, LucideIcons.target, 'Goals'),
              item(3, LucideIcons.settings, 'Settings'),
            ],
          ),
        ),
      ),
    );
  }
}
