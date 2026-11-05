import 'package:flutter/widgets.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

const investmentKinds = <String, String>{
  'stock': 'Stocks',
  'etf': 'ETF',
  'mutual_fund': 'Mutual fund',
  'deposit': 'Fixed deposit',
  'savings_scheme': 'Savings scheme',
  'private': 'Private business',
  'gold': 'Gold',
  'property': 'Property',
  'other': 'Other',
};

const payoutFrequencies = <String, String>{
  'monthly': 'Monthly',
  'quarterly': 'Quarterly',
  'half_year': 'Every half year',
  'yearly': 'Yearly',
};

const contributionFrequencies = <String, String>{
  'daily': 'Daily',
  'weekly': 'Weekly',
  'monthly': 'Monthly',
  'quarterly': 'Quarterly',
  'half_year': 'Every half year',
  'yearly': 'Yearly',
};

const calendarTypes = <String, String>{'payout': 'Payout', 'installment': 'Installment', 'maturity': 'Matures'};

bool takesInstallments(String kind) => const {'deposit', 'savings_scheme'}.contains(kind);

String rateText(double value) => '${value.toStringAsFixed(2).replaceFirst(RegExp(r'\.?0+$'), '')}%';

bool tracksUnits(String kind) => const {'stock', 'etf', 'mutual_fund', 'gold'}.contains(kind);

bool earnsInterest(String kind) => const {'deposit', 'savings_scheme', 'private'}.contains(kind);

/// The words for each activity, so a fixed deposit says "Add money" where shares say "Buy".
Map<String, String> eventLabels(String kind) {
  final units = tracksUnits(kind);
  final interest = earnsInterest(kind);
  return {
    'buy': interest ? 'Add money' : 'Buy',
    'sell': interest ? 'Withdraw' : 'Sell',
    'income': units && kind != 'gold' ? 'Dividend' : (interest ? 'Payout' : 'Income'),
    'valuation': units ? 'Update price' : 'Update value',
  };
}

IconData kindIcon(String kind) => switch (kind) {
  'stock' || 'etf' => LucideIcons.chartCandlestick,
  'mutual_fund' => LucideIcons.chartPie,
  'deposit' => LucideIcons.landmark,
  'savings_scheme' => LucideIcons.piggyBank,
  'private' => LucideIcons.handshake,
  'gold' => LucideIcons.gem,
  'property' => LucideIcons.house,
  _ => LucideIcons.sprout,
};

String kindColor(String kind) => switch (kind) {
  'stock' || 'etf' => 'sky',
  'mutual_fund' => 'indigo',
  'deposit' => 'teal',
  'savings_scheme' => 'emerald',
  'private' => 'orange',
  'gold' => 'amber',
  'property' => 'rose',
  _ => 'cyan',
};
