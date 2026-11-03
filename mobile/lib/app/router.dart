import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../api/models.dart';
import '../features/accounts/accounts_screen.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/session.dart';
import '../features/dashboard/dashboard_screen.dart';
import '../features/ledger/ledger.dart';
import '../features/notifications/notifications_screen.dart';
import '../features/planning/planning_screen.dart';
import '../features/settings/settings_screen.dart';
import '../features/transactions/transaction_form_screen.dart';
import '../features/transactions/transaction_screen.dart';
import '../features/transactions/transactions_screen.dart';
import '../shared/widgets.dart';
import 'theme.dart';

final _rootKey = GlobalKey<NavigatorState>();

final routerProvider = Provider<GoRouter>((ref) {
  final signedIn = ValueNotifier(ref.read(sessionProvider).value != null);
  ref.listen(sessionProvider, (_, next) => signedIn.value = next.value != null);
  ref.onDispose(signedIn.dispose);

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
        pageBuilder: (_, state) => MaterialPage(key: state.pageKey, fullscreenDialog: true, child: const TransactionFormScreen()),
      ),
      GoRoute(parentNavigatorKey: _rootKey, path: '/notifications', builder: (_, _) => const NotificationsScreen()),
      GoRoute(parentNavigatorKey: _rootKey, path: '/settings', builder: (_, _) => const SettingsScreen()),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => _Shell(shell),
        branches: [
          StatefulShellBranch(
            routes: [GoRoute(path: '/', builder: (_, _) => const DashboardScreen())],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/transactions',
                builder: (_, _) => const TransactionsScreen(),
                routes: [
                  GoRoute(
                    parentNavigatorKey: _rootKey,
                    path: ':id',
                    builder: (_, state) => TransactionScreen(groupId: int.parse(state.pathParameters['id']!)),
                    routes: [
                      GoRoute(
                        parentNavigatorKey: _rootKey,
                        path: 'edit',
                        builder: (_, state) => TransactionFormScreen(groupId: int.parse(state.pathParameters['id']!)),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/accounts',
                builder: (_, _) => const AccountsScreen(),
                routes: [
                  GoRoute(
                    parentNavigatorKey: _rootKey,
                    path: ':id',
                    builder: (_, state) =>
                        AccountScreen(accountId: int.parse(state.pathParameters['id']!), initial: state.extra as Account?),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/planning', builder: (_, _) => const PlanningScreen())],
          ),
        ],
      ),
    ],
  );
});

class _Shell extends ConsumerWidget {
  const _Shell(this.shell);

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ledger = ref.watch(ledgerProvider);
    final palette = Palette.of(context);

    return switch (ledger) {
      AsyncValue(hasValue: true, value: null) => Scaffold(
        body: EmptyState(
          icon: LucideIcons.bookOpen,
          title: 'No ledger yet',
          message: 'Create a ledger in the web app, then refresh here.',
          action: OutlinedButton(onPressed: () => ref.invalidate(ledgersProvider), child: const Text('Refresh')),
        ),
      ),
      AsyncValue(hasValue: true, :final value?) => Scaffold(
        body: shell,
        floatingActionButton: value.canEdit && shell.currentIndex < 3
            ? FloatingActionButton(
                tooltip: 'Add transaction',
                elevation: 2,
                backgroundColor: Theme.of(context).colorScheme.primary,
                foregroundColor: Theme.of(context).colorScheme.onPrimary,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                onPressed: () => context.push('/new'),
                child: const Icon(LucideIcons.plus, size: 24),
              )
            : null,
        bottomNavigationBar: DecoratedBox(
          decoration: BoxDecoration(
            border: Border(top: BorderSide(color: palette.border)),
          ),
          child: NavigationBar(
            selectedIndex: shell.currentIndex,
            onDestinationSelected: (index) => shell.goBranch(index, initialLocation: index == shell.currentIndex),
            destinations: const [
              NavigationDestination(icon: Icon(LucideIcons.layoutDashboard), label: 'Dashboard'),
              NavigationDestination(icon: Icon(LucideIcons.arrowLeftRight), label: 'Transactions'),
              NavigationDestination(icon: Icon(LucideIcons.wallet), label: 'Accounts'),
              NavigationDestination(icon: Icon(LucideIcons.chartPie), label: 'Planning'),
            ],
          ),
        ),
      ),
      AsyncValue(:final error?) => Scaffold(
        body: AsyncView(
          value: AsyncError<void>(error, StackTrace.empty),
          onRetry: () => ref.invalidate(ledgersProvider),
          builder: (_) => const SizedBox(),
        ),
      ),
      _ => Scaffold(body: Center(child: LogoMark(size: 48))),
    };
  }
}
