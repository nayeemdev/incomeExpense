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
import '../features/widgets/widget_sync.dart';
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
        pageBuilder: (_, state) => _sheet(
          state,
          QuickAddScreen(
            type: const ['withdrawal', 'deposit', 'transfer'].contains(state.uri.queryParameters['type'])
                ? state.uri.queryParameters['type']!
                : 'withdrawal',
            amount: state.uri.queryParameters['amount'],
            title: state.uri.queryParameters['title'],
          ),
        ),
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
      AsyncValue(hasValue: true, :final value?) => Consumer(
        builder: (context, ref, child) {
          ref.watch(widgetSyncProvider);
          return child!;
        },
        child: Scaffold(
          extendBody: true,
          body: shell,
          bottomNavigationBar: _BottomBar(shell: shell, canAdd: value.canEdit),
        ),
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

/// A small floating pill, like recent iOS apps, with the add button as a solid circle in the middle.
class _BottomBar extends StatelessWidget {
  const _BottomBar({required this.shell, required this.canAdd});

  final StatefulNavigationShell shell;
  final bool canAdd;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final dark = Theme.of(context).brightness == Brightness.dark;

    Widget item(int index, IconData icon, String label) {
      final selected = shell.currentIndex == index;
      return Expanded(
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () {
            HapticFeedback.selectionClick();
            shell.goBranch(index, initialLocation: index == shell.currentIndex);
          },
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
            margin: const EdgeInsets.symmetric(horizontal: 2),
            decoration: BoxDecoration(color: selected ? palette.primaryTint : Colors.transparent, borderRadius: BorderRadius.circular(99)),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, size: 21, color: selected ? palette.primary : palette.muted),
                const SizedBox(height: 2),
                Text(
                  label,
                  maxLines: 1,
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                    color: selected ? palette.primary : palette.muted,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return SafeArea(
      top: false,
      minimum: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Container(
          height: 62,
          padding: const EdgeInsets.all(5),
          decoration: BoxDecoration(
            color: palette.card,
            borderRadius: BorderRadius.circular(99),
            border: Border.all(color: palette.border),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: dark ? 0.4 : 0.08),
                blurRadius: 24,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            children: [
              item(0, LucideIcons.layoutDashboard, 'Dashboard'),
              item(1, LucideIcons.chartColumn, 'Reports'),
              if (canAdd)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Material(
                    color: palette.primary,
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: () {
                        HapticFeedback.mediumImpact();
                        context.push('/new');
                      },
                      child: const SizedBox.square(dimension: 52, child: Icon(LucideIcons.plus, color: Colors.white, size: 26)),
                    ),
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
