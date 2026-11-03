import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';

final notificationsProvider = FutureProvider.autoDispose<List<Notice>>((ref) async {
  final response = await ref.watch(apiProvider).get('notifications', query: {'per_page': 50});
  return (response['data'] as List).map((json) => Notice.fromJson(json as Json)).toList();
});

final unreadCountProvider = FutureProvider.autoDispose<int>((ref) async {
  final response = await ref.watch(apiProvider).get('notifications', query: {'unread': 1, 'per_page': 1});
  return response['meta']['unread'] as int;
});

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  Future<void> _run(BuildContext context, WidgetRef ref, Future<void> Function() action) async {
    try {
      await action();
      ref
        ..invalidate(notificationsProvider)
        ..invalidate(unreadCountProvider);
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.watch(apiProvider);
    final notices = ref.watch(notificationsProvider);
    final hasUnread = notices.value?.any((notice) => notice.readAt == null) ?? false;
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (hasUnread)
            TextButton(onPressed: () => _run(context, ref, () => api.post('notifications/read-all')), child: const Text('Mark all read')),
        ],
      ),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.refresh(notificationsProvider.future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: AsyncView(
                value: notices,
                onRetry: () => ref.invalidate(notificationsProvider),
                loading: const SkeletonList(rows: 3),
                builder: (notices) => SurfaceCard(
                  child: notices.isEmpty
                      ? const EmptyState(
                          icon: LucideIcons.bellOff,
                          title: 'You are all caught up',
                          message: 'New updates will show up here.',
                        )
                      : Column(
                          children: [
                            for (final (index, notice) in notices.indexed) ...[
                              if (index > 0) const Divider(indent: 64),
                              Dismissible(
                                key: ValueKey(notice.id),
                                direction: DismissDirection.endToStart,
                                background: Container(
                                  color: palette.negative,
                                  alignment: Alignment.centerRight,
                                  padding: const EdgeInsets.only(right: 20),
                                  child: const Icon(LucideIcons.trash2, color: Colors.white, size: 20),
                                ),
                                onDismissed: (_) => _run(context, ref, () => api.delete('notifications/${notice.id}')),
                                child: InkWell(
                                  onTap: notice.readAt == null
                                      ? () => _run(context, ref, () => api.post('notifications/${notice.id}/read'))
                                      : null,
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        CategoryIcon(glyph: LucideIcons.bell, color: notice.readAt == null ? 'blue' : 'slate', size: 40),
                                        const SizedBox(width: 14),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                children: [
                                                  Expanded(
                                                    child: Text(
                                                      notice.title,
                                                      style: text.titleSmall?.copyWith(
                                                        fontWeight: notice.readAt == null ? FontWeight.w600 : FontWeight.w500,
                                                      ),
                                                    ),
                                                  ),
                                                  Text(formatDay(notice.createdAt), style: text.bodySmall?.copyWith(color: palette.muted)),
                                                ],
                                              ),
                                              const SizedBox(height: 2),
                                              Text(notice.body, style: text.bodyMedium?.copyWith(color: palette.muted)),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ],
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
