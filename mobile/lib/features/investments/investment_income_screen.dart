import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger_data.dart';
import 'investment_labels.dart';

class InvestmentIncomeScreen extends ConsumerStatefulWidget {
  const InvestmentIncomeScreen({super.key});

  @override
  ConsumerState<InvestmentIncomeScreen> createState() => _InvestmentIncomeScreenState();
}

class _InvestmentIncomeScreenState extends ConsumerState<InvestmentIncomeScreen> {
  int _year = DateTime.now().year;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final thisYear = DateTime.now().year;

    return Scaffold(
      appBar: AppBar(title: const Text('Income report')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.refresh(investmentIncomeProvider(_year).future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                IconButton(tooltip: 'Previous year', icon: const Icon(LucideIcons.chevronLeft), onPressed: () => setState(() => _year--)),
                SizedBox(
                  width: 90,
                  child: Text('$_year', textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge),
                ),
                IconButton(
                  tooltip: 'Next year',
                  icon: const Icon(LucideIcons.chevronRight),
                  onPressed: _year < thisYear ? () => setState(() => _year++) : null,
                ),
              ],
            ),
            const SizedBox(height: 8),
            AsyncView(
              value: ref.watch(investmentIncomeProvider(_year)),
              onRetry: () => ref.invalidate(investmentIncomeProvider(_year)),
              builder: (report) => Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SurfaceCard(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Income received', style: TextStyle(color: palette.muted, fontSize: 13)),
                        const SizedBox(height: 4),
                        BigAmount(report.income, report.currency, size: 32),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Text('Gains from sales', style: TextStyle(color: palette.muted)),
                            const Spacer(),
                            MoneyText(report.realized, report.currency),
                          ],
                        ),
                        if (!report.complete) ...[
                          const SizedBox(height: 10),
                          Text(
                            'Some amounts are left out of the totals because there is no exchange rate for them.',
                            style: TextStyle(color: palette.warning, fontSize: 12.5),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SectionHeader('By investment'),
                  SurfaceCard(
                    child: report.rows.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.all(20),
                            child: Text('No income or sales in $_year.', style: TextStyle(color: palette.muted)),
                          )
                        : Column(
                            children: [
                              for (final row in report.rows)
                                ListRow(
                                  leading: CategoryIcon(glyph: kindIcon(row.kind), color: kindColor(row.kind), size: 40),
                                  title: row.name,
                                  subtitle: [
                                    investmentKinds[row.kind] ?? row.kind,
                                    if ((double.tryParse(row.realized) ?? 0) != 0)
                                      'Sales ${formatMoney(row.realized, row.currency, signed: true)}',
                                  ].join(' · '),
                                  onTap: () => context.push('/investments/${row.id}'),
                                  trailing: MoneyText(row.income, row.currency, tone: MoneyTone.none),
                                ),
                            ],
                          ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
