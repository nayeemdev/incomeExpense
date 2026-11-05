import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import 'investment_calendar_screen.dart';
import 'investment_forms.dart';
import 'investment_labels.dart';
import 'return_roadmap.dart';

String percentText(double? value) => value == null ? '' : '${value > 0 ? '+' : ''}${value.toStringAsFixed(2)}%';

Color gainColor(Palette palette, String amount) {
  final value = double.tryParse(amount) ?? 0;
  return value < 0 ? palette.negative : (value > 0 ? palette.positive : palette.muted);
}

String unitsText(double value) =>
    value == value.roundToDouble() ? value.toStringAsFixed(0) : value.toStringAsFixed(4).replaceFirst(RegExp(r'0+$'), '');

class InvestmentsScreen extends ConsumerWidget {
  const InvestmentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canEdit = ref.watch(currentLedgerProvider).canEdit;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Investments'),
        actions: [
          if (canEdit) TextButton(onPressed: () => showInvestmentSheet(context, ref), child: const Text('Add new')),
          PopupMenuButton<String>(
            tooltip: 'More',
            icon: const Icon(LucideIcons.ellipsisVertical, size: 20),
            onSelected: (path) => context.push(path),
            itemBuilder: (_) => [
              for (final (path, icon, label) in [
                ('/investments/calendar', LucideIcons.calendarDays, 'Calendar'),
                ('/investments/income', LucideIcons.receiptText, 'Income report'),
                ('/investments/what-if', LucideIcons.calculator, 'What-if calculator'),
                ('/zakat', LucideIcons.handCoins, 'Zakat'),
              ])
                PopupMenuItem(
                  value: path,
                  child: Row(children: [Icon(icon, size: 18), const SizedBox(width: 12), Text(label)]),
                ),
            ],
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.refresh(portfolioProvider.future),
        child: AsyncView(
          value: ref.watch(portfolioProvider),
          onRetry: () => ref.invalidate(portfolioProvider),
          builder: (portfolio) => portfolio.investments.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 60),
                    EmptyState(
                      icon: LucideIcons.trendingUp,
                      title: 'No investments yet',
                      message: 'Track shares, a fixed deposit, gold, or money put into a business that pays you every quarter.',
                      action: canEdit
                          ? FilledButton(onPressed: () => showInvestmentSheet(context, ref), child: const Text('Add an investment'))
                          : null,
                    ),
                  ],
                )
              : _PortfolioBody(portfolio),
        ),
      ),
    );
  }
}

class _PortfolioBody extends ConsumerStatefulWidget {
  const _PortfolioBody(this.portfolio);

  final Portfolio portfolio;

  @override
  ConsumerState<_PortfolioBody> createState() => _PortfolioBodyState();
}

class _PortfolioBodyState extends ConsumerState<_PortfolioBody> {
  int? _selected;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final portfolio = widget.portfolio;
    final canEdit = ref.watch(currentLedgerProvider).canEdit;
    final comingUp = (ref.watch(investmentCalendarProvider(12)).value ?? const <CalendarItem>[]).take(4).toList();
    final targets = {for (final target in portfolio.targets) investmentKinds[target.kind] ?? target.kind: target.percent};
    final open = portfolio.investments.where((investment) => investment.closedAt == null).toList();
    final closed = portfolio.investments.where((investment) => investment.closedAt != null).toList();
    final slices = donutSlices(context, [
      for (final item in portfolio.allocation) (name: investmentKinds[item.kind] ?? item.kind, value: item.value),
    ]);
    final total = slices.fold<double>(0, (sum, slice) => sum + slice.value);
    final gainTone = gainColor(palette, portfolio.gain);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
      children: [
        SurfaceCard(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Portfolio value', style: TextStyle(color: palette.muted, fontSize: 13)),
              const SizedBox(height: 4),
              BigAmount(portfolio.value, portfolio.currency, size: 34),
              const SizedBox(height: 6),
              Text(
                '${formatMoney(portfolio.gain, portfolio.currency, signed: true)}  ${percentText(portfolio.gainPercent)}',
                style: TextStyle(color: gainTone, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(child: _Stat('Invested', formatMoney(portfolio.cost, portfolio.currency))),
                  Expanded(child: _Stat('Income received', formatMoney(portfolio.income, portfolio.currency))),
                  if (portfolio.annualReturn != null) Expanded(child: _Stat('Yearly return', percentText(portfolio.annualReturn))),
                ],
              ),
            ],
          ),
        ),
        if (slices.isNotEmpty) ...[
          SectionHeader(
            'Mix',
            action: canEdit ? (targets.isEmpty ? 'Set targets' : 'Edit targets') : null,
            onAction: () => showTargetsSheet(context, portfolio.targets),
          ),
          SurfaceCard(
            padding: const EdgeInsets.all(18),
            child: Row(
              children: [
                DonutChart(
                  slices: slices,
                  size: 130,
                  selected: _selected,
                  onTap: (index) => setState(() => _selected = _selected == index ? null : index),
                  center: Text(
                    _selected == null ? 'Mix' : '${(slices[_selected!].value / total * 100).round()}%',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                const SizedBox(width: 18),
                Expanded(
                  child: Column(
                    children: [
                      for (final slice in slices) _MixRow(slice.name, slice.color, slice.value / total * 100, targets[slice.name]),
                      for (final MapEntry(key: name, value: target) in targets.entries)
                        if (!slices.any((slice) => slice.name == name)) _MixRow(name, palette.subtle, 0, target),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
        if (comingUp.isNotEmpty) ...[
          SectionHeader('Coming up', action: 'See all', onAction: () => context.push('/investments/calendar')),
          SurfaceCard(child: Column(children: [for (final item in comingUp) CalendarRow(item)])),
        ],
        if (open.isNotEmpty) ...[
          const SectionHeader('Holdings'),
          SurfaceCard(child: Column(children: [for (final investment in open) _InvestmentRow(investment)])),
        ],
        if (closed.isNotEmpty) ...[
          const SectionHeader('Closed'),
          SurfaceCard(child: Column(children: [for (final investment in closed) _InvestmentRow(investment)])),
        ],
      ],
    );
  }
}

class _MixRow extends StatelessWidget {
  const _MixRow(this.name, this.color, this.percent, this.target);

  final String name;
  final Color color;
  final double percent;
  final double? target;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final off = target != null && (percent - target!).abs() >= 5;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Container(
            width: 10,
            height: 10,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13)),
                if (target != null)
                  Text('Target ${rateText(target!)}', style: TextStyle(color: off ? palette.warning : palette.subtle, fontSize: 11.5)),
              ],
            ),
          ),
          Text('${percent.round()}%', style: TextStyle(color: palette.muted, fontSize: 13)),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(color: Palette.of(context).muted, fontSize: 12.5)),
        const SizedBox(height: 2),
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ),
      ],
    );
  }
}

class _InvestmentRow extends StatelessWidget {
  const _InvestmentRow(this.investment);

  final Investment investment;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final overdue = investment.upcomingPayouts.any((payout) => payout.overdue);
    final subtitle = [
      investmentKinds[investment.kind],
      if (investment.tracksUnits && investment.units > 0) '${unitsText(investment.units)} units',
      if (overdue) 'Payout to record',
    ].join(' · ');

    return ListRow(
      leading: CategoryIcon(glyph: kindIcon(investment.kind), color: kindColor(investment.kind), size: 40),
      title: investment.name,
      subtitle: subtitle,
      onTap: () => context.push('/investments/${investment.id}'),
      trailing: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          MoneyText(investment.value, investment.currency.code, tone: MoneyTone.none),
          if (investment.gainPercent != null)
            Text(percentText(investment.gainPercent), style: TextStyle(fontSize: 12, color: gainColor(palette, investment.gain))),
        ],
      ),
    );
  }
}

class InvestmentScreen extends ConsumerWidget {
  const InvestmentScreen({super.key, required this.investmentId});

  final String investmentId;

  Future<void> _delete(BuildContext context, WidgetRef ref, Investment investment) async {
    final confirmed = await showConfirm(
      context,
      icon: LucideIcons.trash2,
      title: 'Delete ${investment.name}?',
      message: 'Every transaction booked for it is deleted too. To keep the history, sell or withdraw everything instead.',
      confirm: 'Delete',
    );
    if (!confirmed || !context.mounted) return;
    try {
      await ref.read(apiProvider).delete(ledgerPath(ref.read(currentLedgerProvider), 'investments/${investment.id}'));
      refreshLedgerData(ref.invalidate);
      if (context.mounted) closeScreen(context);
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }

  Future<void> _deleteEvent(BuildContext context, WidgetRef ref, InvestmentEvent event) async {
    final confirmed = await showConfirm(
      context,
      icon: LucideIcons.trash2,
      title: 'Delete this entry?',
      message: 'Any transaction booked with it is deleted too.',
      confirm: 'Delete',
    );
    if (!confirmed || !context.mounted) return;
    try {
      await ref.read(apiProvider).delete(ledgerPath(ref.read(currentLedgerProvider), 'investments/$investmentId/events/${event.id}'));
      refreshLedgerData(ref.invalidate);
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }

  Future<void> _chooseRecord(BuildContext context, WidgetRef ref, Investment investment) async {
    final labels = eventLabels(investment.kind);
    final type = await showAppSheet<String>(
      context,
      title: 'Record',
      builder: (context) => ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.only(bottom: 16),
        children: [
          for (final (type, icon) in [
            ('income', LucideIcons.banknoteArrowUp),
            if (investment.held) ('valuation', LucideIcons.chartLine),
            if (investment.held) ('sell', LucideIcons.banknoteArrowDown),
            if (investment.held && !investment.tracksUnits && investment.closedAt == null) ('renew', LucideIcons.refreshCw),
          ])
            ListRow(
              leading: IconBadge(icon, color: Palette.of(context).primary, size: 40),
              title: labels[type] ?? 'Renew for a new term',
              onTap: () => Navigator.pop(context, type),
            ),
        ],
      ),
    );
    if (type == null || !context.mounted) return;
    if (type == 'renew') {
      await showRenewSheet(context, investment);
    } else {
      await showEventSheet(context, investment, type);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final canEdit = ref.watch(currentLedgerProvider).canEdit;
    final value = ref.watch(investmentProvider(investmentId));
    final investment = value.value;

    return Scaffold(
      appBar: AppBar(
        title: Text(investment?.name ?? 'Investment'),
        actions: [
          if (investment != null && canEdit) ...[
            IconButton(
              tooltip: 'Edit',
              icon: const Icon(LucideIcons.pencil, size: 20),
              onPressed: () => showInvestmentSheet(context, ref, investment: investment),
            ),
            IconButton(
              tooltip: 'Delete',
              icon: const Icon(LucideIcons.trash2, size: 20),
              onPressed: () => _delete(context, ref, investment),
            ),
          ],
          const SizedBox(width: 4),
        ],
      ),
      body: AsyncView(
        value: value,
        onRetry: () => ref.invalidate(investmentProvider(investmentId)),
        builder: (investment) {
          final currency = investment.currency.code;
          final labels = eventLabels(investment.kind);
          final gainTone = gainColor(palette, investment.gain);
          final accountNames = {
            for (final account in ref.watch(accountsProvider('asset')).value ?? const <Account>[]) account.id: account.name,
          };

          return RefreshIndicator.adaptive(
            onRefresh: () => ref.refresh(investmentProvider(investmentId).future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
              children: [
                SurfaceCard(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        [
                          investmentKinds[investment.kind],
                          investment.symbol,
                          if (investment.closedAt != null) 'Closed',
                        ].whereType<String>().join(' · '),
                        style: TextStyle(color: palette.muted, fontSize: 13),
                      ),
                      const SizedBox(height: 4),
                      BigAmount(investment.value, currency, size: 34),
                      const SizedBox(height: 6),
                      Text(
                        '${formatMoney(investment.gain, currency, signed: true)}  ${percentText(investment.gainPercent)}',
                        style: TextStyle(color: gainTone, fontWeight: FontWeight.w600),
                      ),
                      if (investment.annualReturn != null) ...[
                        const SizedBox(height: 2),
                        Text('${percentText(investment.annualReturn)} a year', style: TextStyle(color: palette.muted, fontSize: 13)),
                      ],
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(child: _Stat('Invested', formatMoney(investment.cost, currency))),
                          Expanded(child: _Stat('Income', formatMoney(investment.income, currency))),
                          Expanded(child: _Stat('From sales', formatMoney(investment.realized, currency))),
                        ],
                      ),
                    ],
                  ),
                ),
                if (canEdit) ...[
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _chooseRecord(context, ref, investment),
                          icon: const Icon(LucideIcons.notebookPen, size: 18),
                          label: const Text('Record'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: () => showEventSheet(context, investment, 'buy'),
                          icon: const Icon(LucideIcons.plus, size: 18),
                          label: Text(labels['buy']!),
                        ),
                      ),
                    ],
                  ),
                ],
                if (investment.roadmap != null) ...[
                  const SectionHeader('Return roadmap'),
                  SurfaceCard(
                    padding: const EdgeInsets.all(16),
                    child: ReturnRoadmap(roadmap: investment.roadmap!, invested: investment.invested, currency: currency),
                  ),
                ],
                if (investment.contributors.isNotEmpty) ...[
                  const SectionHeader('Partners'),
                  SurfaceCard(
                    child: Column(
                      children: [
                        for (final partner in investment.contributors)
                          ListRow(
                            leading: IconBadge(LucideIcons.user, color: palette.primary, size: 40),
                            title: partner.name ?? 'Not set',
                            subtitle: '${rateText(partner.share)} share · Income ${formatMoney(partner.income, currency)}',
                            trailing: MoneyText(partner.value, currency, tone: MoneyTone.none),
                          ),
                      ],
                    ),
                  ),
                ],
                if (investment.upcomingPayouts.isNotEmpty) ...[
                  const SectionHeader('Expected payouts'),
                  SurfaceCard(
                    child: Column(
                      children: [
                        for (final payout in investment.upcomingPayouts)
                          ListRow(
                            leading: IconBadge(
                              payout.overdue ? LucideIcons.clockAlert : LucideIcons.calendarClock,
                              color: payout.overdue ? palette.warning : palette.primary,
                              size: 40,
                            ),
                            title: formatDate(payout.date),
                            subtitle: payout.overdue ? 'Not recorded yet' : null,
                            trailing: canEdit && !payout.date.isAfter(DateTime.now())
                                ? TextButton(
                                    onPressed: () =>
                                        showEventSheet(context, investment, 'income', date: payout.date, amount: payout.amount),
                                    child: const Text('Record'),
                                  )
                                : payout.amount == null
                                ? null
                                : MoneyText(payout.amount!, currency, tone: MoneyTone.none),
                          ),
                      ],
                    ),
                  ),
                ],
                const SectionHeader('Activity'),
                SurfaceCard(
                  child: investment.events.isEmpty
                      ? Padding(
                          padding: const EdgeInsets.all(20),
                          child: Text('Nothing recorded yet.', style: TextStyle(color: palette.muted)),
                        )
                      : Column(
                          children: [
                            for (final event in investment.events)
                              ListRow(
                                leading: IconBadge(_eventIcon(event.type), color: palette.primary, size: 40),
                                title: labels[event.type] ?? event.type,
                                subtitle: [
                                  formatDate(event.date),
                                  if (event.reinvested) 'Kept invested',
                                  if (event.userName != null && event.type == 'buy') 'By ${event.userName}',
                                  if (event.units != null) '${unitsText(double.parse(event.units!))} units',
                                  if (event.gain != null && double.parse(event.gain!) != 0)
                                    'Gain ${formatMoney(event.gain!, currency, signed: true)}',
                                ].join(' · '),
                                onTap: canEdit ? () => _deleteEvent(context, ref, event) : null,
                                trailing: MoneyText(
                                  event.amount,
                                  currency,
                                  tone: event.type == 'income' ? MoneyTone.positive : MoneyTone.none,
                                ),
                              ),
                          ],
                        ),
                ),
                const SectionHeader('Details'),
                SurfaceCard(
                  child: Column(
                    children: [
                      if (investment.interestRate != null) _Fact('Yearly return', rateText(investment.interestRate!)),
                      if (investment.taxRate != null) _Fact('Tax on profit', rateText(investment.taxRate!)),
                      if (investment.annualReturn != null) _Fact('Return so far', '${percentText(investment.annualReturn)} a year'),
                      if (investment.interestRate != null)
                        _Fact('Pays out', payoutFrequencies[investment.payoutFrequency] ?? 'At maturity'),
                      if (investment.payoutAmount != null) _Fact('Each payout', formatMoney(investment.payoutAmount!, currency)),
                      if (investment.contribution case final installment?) ...[
                        _Fact(
                          'Installment',
                          '${formatMoney(installment.amount, currency)} ${(contributionFrequencies[installment.frequency] ?? installment.frequency).toLowerCase()}',
                        ),
                        if (installment.nextDate != null) _Fact('Next installment', formatDate(installment.nextDate!)),
                        if (accountNames[installment.accountId] case final account?) _Fact('Paid from', account),
                      ],
                      if (investment.startDate != null) _Fact('Start date', formatDate(investment.startDate!)),
                      if (investment.maturityDate != null) _Fact('Maturity date', formatDate(investment.maturityDate!)),
                      if (investment.tracksUnits && investment.price != null)
                        _Fact('Latest price', formatMoney(investment.price!, currency)),
                      _Fact('Total put in', formatMoney(investment.invested, currency)),
                      if (investment.notes != null) _Fact('Notes', investment.notes!),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

IconData _eventIcon(String type) => switch (type) {
  'buy' => LucideIcons.plus,
  'sell' => LucideIcons.banknoteArrowDown,
  'income' => LucideIcons.banknoteArrowUp,
  _ => LucideIcons.chartLine,
};

class _Fact extends StatelessWidget {
  const _Fact(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(color: Palette.of(context).muted)),
          const SizedBox(width: 16),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
