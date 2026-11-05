import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';

const _goldPriceKey = 'zakat_gold_price';

const _groups = <String, String>{
  'cash': 'Cash and bank',
  'investments': 'Investments',
  'gold': 'Gold',
  'receivables': 'Owed to you',
  'debts': 'What you owe',
};

class ZakatScreen extends ConsumerStatefulWidget {
  const ZakatScreen({super.key});

  @override
  ConsumerState<ZakatScreen> createState() => _ZakatScreenState();
}

class _ZakatScreenState extends ConsumerState<ZakatScreen> {
  final _price = TextEditingController();
  Zakat? _result;
  bool _working = false;

  @override
  void initState() {
    super.initState();
    ref.read(storageProvider).read(key: _goldPriceKey).then((saved) {
      if (!mounted || saved == null || _price.text.isNotEmpty) return;
      setState(() => _price.text = saved);
      _calculate();
    });
  }

  @override
  void dispose() {
    _price.dispose();
    super.dispose();
  }

  Future<void> _calculate() async {
    final price = _price.text.trim();
    if ((double.tryParse(price) ?? 0) <= 0) return;
    FocusScope.of(context).unfocus();
    setState(() => _working = true);
    try {
      final response = await ref.read(apiProvider).get(ledgerPath(ref.read(currentLedgerProvider), 'zakat'), query: {'gold_price': price});
      await ref.read(storageProvider).write(key: _goldPriceKey, value: price);
      if (mounted) setState(() => _result = Zakat.fromJson(response['data'] as Json));
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _working = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final currency = ref.watch(currentLedgerProvider).currency.code;
    final result = _result;
    final ready = (double.tryParse(_price.text.trim()) ?? 0) > 0 && !_working;

    return Scaffold(
      appBar: AppBar(title: const Text('Zakat')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
        children: [
          Text(
            'Works out zakat on cash, investments, gold and money owed to you, less what you owe. The home you live in is left out.',
            style: TextStyle(color: palette.muted, fontSize: 13),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Gold price per gram', required: true),
          TextField(
            controller: _price,
            onChanged: (_) => setState(() {}),
            onSubmitted: (_) => _calculate(),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(hintText: 'Today\'s price', suffixText: currency),
          ),
          const SizedBox(height: 16),
          FilledButton(onPressed: ready ? _calculate : null, child: Text(_working ? 'Working it out...' : 'Work it out')),
          if (result != null) ...[
            const SizedBox(height: 16),
            SurfaceCard(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      IconBadge(
                        result.due ? LucideIcons.handCoins : LucideIcons.circleCheck,
                        color: result.due ? palette.primary : palette.positive,
                        size: 36,
                      ),
                      const SizedBox(width: 10),
                      Expanded(child: Text(result.due ? 'Zakat is due' : 'No zakat due', style: Theme.of(context).textTheme.titleMedium)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  BigAmount(result.zakat, result.currency, size: 34),
                  const SizedBox(height: 4),
                  Text(
                    result.due
                        ? '${_figure(result.rate)} of ${formatMoney(result.total, result.currency)}'
                        : 'Your wealth is below the nisab of ${formatMoney(result.nisab, result.currency)}.',
                    style: TextStyle(color: palette.muted, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(child: _Stat('Zakatable wealth', formatMoney(result.total, result.currency))),
                      Expanded(
                        child: _Stat(
                          'Nisab (${_figure(result.nisabGoldGrams, suffix: ' g')} gold)',
                          formatMoney(result.nisab, result.currency),
                        ),
                      ),
                    ],
                  ),
                  if (!result.complete) ...[
                    const SizedBox(height: 12),
                    Text(
                      'Some balances are left out because there is no exchange rate for their currency.',
                      style: TextStyle(color: palette.warning, fontSize: 12.5),
                    ),
                  ],
                ],
              ),
            ),
            for (final MapEntry(key: group, value: label) in _groups.entries)
              if (result.items.where((item) => item.group == group).toList() case final items when items.isNotEmpty) ...[
                SectionHeader(label),
                SurfaceCard(
                  child: Column(
                    children: [
                      for (final item in items)
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                          child: Row(
                            children: [
                              Expanded(child: Text(item.name, maxLines: 1, overflow: TextOverflow.ellipsis)),
                              const SizedBox(width: 12),
                              MoneyText(item.amount, result.currency),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            const SizedBox(height: 12),
            Text('Worked out for ${formatDate(result.date)}.', style: TextStyle(color: palette.subtle, fontSize: 12)),
          ],
        ],
      ),
    );
  }
}

String _figure(double value, {String suffix = '%'}) => '${value.toStringAsFixed(2).replaceFirst(RegExp(r'\.?0+$'), '')}$suffix';

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
          child: Text(value, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
        ),
      ],
    );
  }
}
