import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger_data.dart';
import 'investment_labels.dart';

const _months = 12;

class InvestmentCalendarScreen extends ConsumerWidget {
  const InvestmentCalendarScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: const Text('Calendar')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.refresh(investmentCalendarProvider(_months).future),
        child: AsyncView(
          value: ref.watch(investmentCalendarProvider(_months)),
          onRetry: () => ref.invalidate(investmentCalendarProvider(_months)),
          builder: (items) {
            if (items.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 60),
                  EmptyState(
                    icon: LucideIcons.calendarDays,
                    title: 'Nothing coming up',
                    message: 'Payouts, installments and maturities for the next year show here.',
                  ),
                ],
              );
            }
            final months = <String, List<CalendarItem>>{};
            for (final item in items) {
              months.putIfAbsent(DateFormat('MMMM y').format(item.date), () => []).add(item);
            }
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 40),
              children: [
                for (final MapEntry(key: month, value: rows) in months.entries) ...[
                  SectionHeader(month),
                  SurfaceCard(child: Column(children: [for (final item in rows) CalendarRow(item)])),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class CalendarRow extends StatelessWidget {
  const CalendarRow(this.item, {super.key});

  final CalendarItem item;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final (icon, color) = switch (item.type) {
      _ when item.overdue => (LucideIcons.clockAlert, palette.warning),
      'installment' => (LucideIcons.calendarSync, palette.primary),
      'maturity' => (LucideIcons.trophy, palette.positive),
      _ => (LucideIcons.calendarClock, palette.primary),
    };
    return ListRow(
      leading: IconBadge(icon, color: color, size: 40),
      title: item.name,
      subtitle: [
        calendarTypes[item.type] ?? sentenceCase(item.type),
        formatDay(item.date),
        if (item.overdue) 'Not recorded yet',
      ].join(' · '),
      onTap: () => context.push('/investments/${item.investmentId}'),
      trailing: item.amount == null
          ? null
          : MoneyText(item.amount!, item.currency, tone: item.type == 'installment' ? MoneyTone.none : MoneyTone.positive),
    );
  }
}
