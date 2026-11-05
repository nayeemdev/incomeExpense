import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import 'investment_forms.dart';
import 'investment_labels.dart';
import 'return_roadmap.dart';

class WhatIfScreen extends ConsumerStatefulWidget {
  const WhatIfScreen({super.key});

  @override
  ConsumerState<WhatIfScreen> createState() => _WhatIfScreenState();
}

class _WhatIfScreenState extends ConsumerState<WhatIfScreen> {
  final _amount = TextEditingController();
  final _rate = TextEditingController();
  final _tax = TextEditingController();
  final _installment = TextEditingController();
  String? _frequency;
  String _installmentFrequency = 'monthly';
  DateTime _start = Dates.today();
  DateTime? _maturity;
  Projection? _result;
  String? _resultAmount;
  bool _working = false;

  @override
  void dispose() {
    for (final controller in [_amount, _rate, _tax, _installment]) {
      controller.dispose();
    }
    super.dispose();
  }

  bool get _hasInstallment => (double.tryParse(_installment.text.trim()) ?? 0) > 0;

  Future<void> _calculate() async {
    FocusScope.of(context).unfocus();
    setState(() => _working = true);
    try {
      final response = await ref.read(apiProvider).post(ledgerPath(ref.read(currentLedgerProvider), 'investments/projection'), {
        'amount': _amount.text.trim(),
        'interest_rate': _rate.text.trim(),
        'payout_frequency': _frequency,
        'tax_rate': _tax.text.trim().isEmpty ? null : _tax.text.trim(),
        'start_date': isoDate(_start),
        'maturity_date': _maturity == null ? null : isoDate(_maturity!),
        'contribution_amount': _hasInstallment ? _installment.text.trim() : null,
        'contribution_frequency': _hasInstallment ? _installmentFrequency : null,
      });
      setState(() {
        _result = Projection.fromJson(response['data'] as Json);
        _resultAmount = _amount.text.trim();
      });
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
    final ready =
        (double.tryParse(_amount.text.trim()) ?? 0) > 0 &&
        double.tryParse(_rate.text.trim()) != null &&
        (_frequency != null || _maturity != null) &&
        !_working;

    return Scaffold(
      appBar: AppBar(title: const Text('What-if calculator')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
        children: [
          Text(
            'See what an offer would pay before you put money in. Nothing is saved.',
            style: TextStyle(color: palette.muted, fontSize: 13),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const FieldLabel('Amount', required: true),
                    TextField(
                      controller: _amount,
                      onChanged: (_) => setState(() {}),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(hintText: '0', suffixText: currency),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const FieldLabel('Yearly return', required: true),
                    TextField(
                      controller: _rate,
                      onChanged: (_) => setState(() {}),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(hintText: '0', suffixText: '%'),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const FieldLabel('Pays out'),
          QuickPicks(
            items: [
              (label: 'At maturity', hint: null, active: _frequency == null, leading: null),
              for (final entry in payoutFrequencies.entries)
                (label: entry.value, hint: null, active: entry.key == _frequency, leading: null),
            ],
            onPick: (index) => setState(() => _frequency = index == 0 ? null : payoutFrequencies.keys.elementAt(index - 1)),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: DateField(label: 'Start date', value: _start, onChanged: (date) => setState(() => _start = date ?? _start)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DateField(
                  label: 'Maturity',
                  value: _maturity,
                  hint: _frequency == null ? 'Required' : 'Optional',
                  onChanged: (date) => setState(() => _maturity = date),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const FieldLabel('Tax on profit'),
          TextField(
            controller: _tax,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(hintText: 'Optional', suffixText: '%'),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Installment'),
          TextField(
            controller: _installment,
            onChanged: (_) => setState(() {}),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(hintText: 'Optional, paid in on a schedule', suffixText: currency),
          ),
          if (_hasInstallment) ...[
            const SizedBox(height: 12),
            QuickPicks(
              items: [
                for (final entry in contributionFrequencies.entries)
                  (label: entry.value, hint: null, active: entry.key == _installmentFrequency, leading: null),
              ],
              onPick: (index) => setState(() => _installmentFrequency = contributionFrequencies.keys.elementAt(index)),
            ),
          ],
          const SizedBox(height: 24),
          FilledButton(onPressed: ready ? _calculate : null, child: Text(_working ? 'Working it out...' : 'Work it out')),
          if (result != null) ...[
            if (result.payoutAmount != null) ...[
              const SizedBox(height: 12),
              SurfaceCard(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Each payout', style: TextStyle(color: palette.muted, fontSize: 13)),
                    const SizedBox(height: 4),
                    BigAmount(result.payoutAmount!, currency, size: 30),
                  ],
                ),
              ),
            ],
            const SectionHeader('Return roadmap'),
            SurfaceCard(
              padding: const EdgeInsets.all(16),
              child: ReturnRoadmap(roadmap: result.roadmap, invested: _resultAmount ?? '0', currency: currency),
            ),
          ],
        ],
      ),
    );
  }
}
