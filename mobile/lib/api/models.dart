typedef Json = Map<String, dynamic>;

List<T> _list<T>(Object? raw, T Function(Json) parse) =>
    raw is List ? raw.whereType<Map>().map((item) => parse(item.cast<String, dynamic>())).toList() : <T>[];

DateTime? _date(Object? raw) => raw is String ? DateTime.tryParse(raw) : null;

class Currency {
  const Currency({required this.id, required this.code, required this.decimals});

  factory Currency.fromJson(Json json) =>
      Currency(id: json['id'] as String, code: json['code'] as String, decimals: json['decimal_places'] as int? ?? 2);

  final String id;
  final String code;
  final int decimals;
}

class Amount {
  const Amount(this.currency, this.value);

  factory Amount.fromJson(Json json) => Amount(json['currency'] as String, json['amount'].toString());

  final String currency;
  final String value;
}

class User {
  const User({required this.id, required this.name, required this.email, required this.twoFactor});

  factory User.fromJson(Json json) => User(
    id: json['id'] as String,
    name: json['name'] as String,
    email: json['email'] as String,
    twoFactor: json['two_factor_enabled'] as bool? ?? false,
  );

  final String id;
  final String name;
  final String email;
  final bool twoFactor;
}

class Ledger {
  const Ledger({
    required this.id,
    required this.name,
    required this.currency,
    required this.role,
    this.defaultAccountId,
    this.defaultIncomeAccountId,
  });

  factory Ledger.fromJson(Json json) => Ledger(
    id: json['id'] as String,
    name: json['name'] as String,
    currency: Currency.fromJson(json['primary_currency'] as Json),
    role: json['role'] as String? ?? 'viewer',
    defaultAccountId: json['default_account_id'] as String?,
    defaultIncomeAccountId: json['default_income_account_id'] as String?,
  );

  final String id;
  final String name;
  final Currency currency;
  final String role;
  final String? defaultAccountId;
  final String? defaultIncomeAccountId;

  /// The account new money is paid from, or received into for income, unless the member picks another.
  String? defaultAccountFor(String type) => type == 'deposit' ? defaultIncomeAccountId ?? defaultAccountId : defaultAccountId;

  bool get canEdit => role == 'owner' || role == 'editor';
}

class Account {
  const Account({
    required this.id,
    required this.name,
    required this.type,
    required this.active,
    this.currency,
    this.balance,
    this.role,
    this.liabilityDirection,
  });

  factory Account.fromJson(Json json) => Account(
    id: json['id'] as String,
    name: json['name'] as String,
    type: json['type'] as String,
    active: json['active'] as bool? ?? true,
    currency: json['currency'] is Map ? Currency.fromJson(json['currency'] as Json) : null,
    balance: json['balance']?.toString(),
    role: (json['asset_role'] ?? json['liability_type']) as String?,
    liabilityDirection: json['liability_direction'] as String?,
  );

  final String id;
  final String name;
  final String type;
  final bool active;
  final Currency? currency;
  final String? balance;
  final String? role;
  final String? liabilityDirection;

  bool get isOwn => type == 'asset' || type == 'liability';
}

class AccountRef {
  const AccountRef({required this.id, required this.name, required this.type});

  factory AccountRef.fromJson(Json json) =>
      AccountRef(id: json['id'] as String, name: json['name'] as String, type: json['type'] as String);

  final String id;
  final String name;
  final String type;
}

/// A reference by id and name. Categories also carry their icon and color.
class NamedRef {
  const NamedRef(this.id, this.name, {this.icon, this.color});

  factory NamedRef.fromJson(Json json) =>
      NamedRef(json['id'] as String, json['name'] as String, icon: json['icon'] as String?, color: json['color'] as String?);

  final String id;
  final String name;
  final String? icon;
  final String? color;
}

class Transaction {
  const Transaction({
    required this.id,
    required this.groupId,
    required this.type,
    required this.date,
    required this.description,
    required this.source,
    required this.destination,
    required this.currency,
    required this.amount,
    this.groupTitle,
    this.category,
    this.budget,
    this.bill,
    this.tags = const [],
    this.notes,
    this.runningBalance,
  });

  factory Transaction.fromJson(Json json) => Transaction(
    id: json['id'] as String,
    groupId: json['group_id'] as String,
    groupTitle: json['group_title'] as String?,
    type: json['type'] as String,
    date: DateTime.parse(json['date'] as String),
    description: json['description'] as String,
    source: AccountRef.fromJson(json['source'] as Json),
    destination: AccountRef.fromJson(json['destination'] as Json),
    currency: Currency.fromJson(json['currency'] as Json),
    amount: json['amount'].toString(),
    category: json['category'] is Map ? NamedRef.fromJson(json['category'] as Json) : null,
    budget: json['budget'] is Map ? NamedRef.fromJson(json['budget'] as Json) : null,
    bill: json['bill'] is Map ? NamedRef.fromJson(json['bill'] as Json) : null,
    tags: _list(json['tags'], (tag) => tag['name'] as String),
    notes: json['notes'] as String?,
    runningBalance: json['running_balance']?.toString(),
  );

  final String id;
  final String groupId;
  final String? groupTitle;
  final String type;
  final DateTime date;
  final String description;
  final AccountRef source;
  final AccountRef destination;
  final Currency currency;
  final String amount;
  final NamedRef? category;
  final NamedRef? budget;
  final NamedRef? bill;
  final List<String> tags;
  final String? notes;
  final String? runningBalance;
}

class TransactionGroup {
  const TransactionGroup({required this.id, required this.title, required this.splits});

  factory TransactionGroup.fromJson(Json json) => TransactionGroup(
    id: json['id'] as String,
    title: json['title'] as String?,
    splits: _list(json['transactions'], Transaction.fromJson),
  );

  final String id;
  final String? title;
  final List<Transaction> splits;
}

class BudgetLimit {
  const BudgetLimit({required this.currency, required this.amount, this.spent});

  factory BudgetLimit.fromJson(Json json) => BudgetLimit(
    currency: Currency.fromJson(json['currency'] as Json),
    amount: json['amount'].toString(),
    spent: json['spent']?.toString(),
  );

  final Currency currency;
  final String amount;
  final String? spent;
}

class Budget {
  const Budget({required this.id, required this.name, required this.limits, required this.spent});

  factory Budget.fromJson(Json json) => Budget(
    id: json['id'] as String,
    name: json['name'] as String,
    limits: _list(json['limits'], BudgetLimit.fromJson),
    spent: _list(json['spent'], Amount.fromJson),
  );

  final String id;
  final String name;
  final List<BudgetLimit> limits;
  final List<Amount> spent;
}

class Bill {
  const Bill({
    required this.id,
    required this.name,
    required this.currency,
    required this.amountMin,
    required this.amountMax,
    required this.repeatFreq,
    required this.expectedDates,
    required this.paidCount,
    this.nextExpected,
    this.group,
  });

  factory Bill.fromJson(Json json) => Bill(
    id: json['id'] as String,
    name: json['name'] as String,
    group: json['object_group'] as String?,
    currency: Currency.fromJson(json['currency'] as Json),
    amountMin: json['amount_min'].toString(),
    amountMax: json['amount_max'].toString(),
    repeatFreq: json['repeat_freq'] as String,
    nextExpected: _date(json['next_expected_date']),
    expectedDates: (json['expected_dates'] as List? ?? const []).map((date) => DateTime.parse(date as String)).toList(),
    paidCount: (json['payments'] as List? ?? const []).length,
  );

  final String id;
  final String name;
  final String? group;
  final Currency currency;
  final String amountMin;
  final String amountMax;
  final String repeatFreq;
  final DateTime? nextExpected;
  final List<DateTime> expectedDates;
  final int paidCount;

  bool get isPaid => expectedDates.isNotEmpty && paidCount >= expectedDates.length;
  bool get isDue => expectedDates.isNotEmpty && !isPaid;
}

class Notice {
  const Notice({required this.id, required this.title, required this.body, required this.createdAt, this.readAt});

  factory Notice.fromJson(Json json) => Notice(
    id: json['id'] as String,
    title: json['title'] as String? ?? '',
    body: json['body'] as String? ?? '',
    createdAt: DateTime.parse(json['created_at'] as String),
    readAt: _date(json['read_at']),
  );

  final String id;
  final String title;
  final String body;
  final DateTime createdAt;
  final DateTime? readAt;
}

class CategoryTotal {
  const CategoryTotal(this.name, this.amount, {this.id, this.icon, this.color});

  final String? id;
  final String? name;
  final Amount amount;
  final String? icon;
  final String? color;
}

class Summary {
  const Summary({required this.netWorth, required this.spent, required this.earned, required this.topCategories, required this.accounts});

  factory Summary.fromJson(Json json) => Summary(
    netWorth: Amount.fromJson(json['net_worth_total'] as Json),
    spent: Amount.fromJson(json['spent_total'] as Json),
    earned: Amount.fromJson(json['earned_total'] as Json),
    topCategories: _list(
      json['top_categories'],
      (row) => CategoryTotal(
        row['name'] as String?,
        Amount.fromJson(row),
        id: row['id'] as String?,
        icon: row['icon'] as String?,
        color: row['color'] as String?,
      ),
    ),
    accounts: _list(json['accounts'], Account.fromJson),
  );

  final Amount netWorth;
  final Amount spent;
  final Amount earned;
  final List<CategoryTotal> topCategories;
  final List<Account> accounts;
}

class Goal {
  const Goal({
    required this.id,
    required this.name,
    required this.currency,
    required this.saved,
    required this.accounts,
    this.target,
    this.targetDate,
  });

  factory Goal.fromJson(Json json) => Goal(
    id: json['id'] as String,
    name: json['name'] as String,
    currency: Currency.fromJson(json['currency'] as Json),
    saved: double.tryParse(json['saved_amount'].toString()) ?? 0,
    target: double.tryParse(json['target_amount']?.toString() ?? ''),
    targetDate: _date(json['target_date']),
    accounts: _list(json['accounts'], (account) => NamedRef(account['id'] as String, account['name'] as String)),
  );

  final String id;
  final String name;
  final Currency currency;
  final double saved;
  final double? target;
  final DateTime? targetDate;
  final List<NamedRef> accounts;

  double get progress => target == null || target == 0 ? 0 : (saved / target!).clamp(0, 1).toDouble();
}

class Bucket {
  const Bucket(this.start, this.income, this.expense);

  final DateTime start;
  final double income;
  final double expense;
}

class Report {
  const Report({required this.currency, required this.buckets, required this.expenseCategories, required this.incomeCategories});

  factory Report.fromJson(Json json) {
    final data = json['data'] as Json;
    CategoryTotal total(Json row, String currency) =>
        CategoryTotal(row['name'] as String?, Amount(currency, row['total'].toString()), id: row['id'] as String?);
    final currency = (json['meta'] as Json)['currency'] as String;
    return Report(
      currency: currency,
      buckets: _list(
        data['income_expense'],
        (row) => Bucket(
          DateTime.parse(row['start'] as String),
          double.tryParse(row['income'].toString()) ?? 0,
          double.tryParse(row['expense'].toString()) ?? 0,
        ),
      ),
      expenseCategories: _list(data['expense_categories'], (row) => total(row, currency)),
      incomeCategories: _list(data['income_categories'], (row) => total(row, currency)),
    );
  }

  final String currency;
  final List<Bucket> buckets;
  final List<CategoryTotal> expenseCategories;
  final List<CategoryTotal> incomeCategories;
}

class Attachment {
  const Attachment({required this.id, required this.filename, required this.mimeType});

  factory Attachment.fromJson(Json json) =>
      Attachment(id: json['id'] as String, filename: json['filename'] as String, mimeType: json['mime_type'] as String? ?? '');

  final String id;
  final String filename;
  final String mimeType;

  bool get isImage => mimeType.startsWith('image/');
}

double _number(Object? raw) => double.tryParse(raw?.toString() ?? '') ?? 0;

class InvestmentEvent {
  const InvestmentEvent({
    required this.id,
    required this.type,
    required this.date,
    required this.amount,
    this.units,
    this.price,
    this.gain,
    this.groupId,
    this.reinvested = false,
    this.userName,
  });

  factory InvestmentEvent.fromJson(Json json) => InvestmentEvent(
    id: json['id'] as String,
    type: json['type'] as String,
    date: _date(json['date'])!,
    amount: json['amount'].toString(),
    units: json['units']?.toString(),
    price: json['price']?.toString(),
    gain: json['gain']?.toString(),
    groupId: json['transaction_group_id'] as String?,
    reinvested: json['reinvested'] as bool? ?? false,
    userName: (json['user'] as Map?)?['name'] as String?,
  );

  final String id;
  final String type;
  final DateTime date;
  final String amount;
  final String? units;
  final String? price;
  final String? gain;
  final String? groupId;
  final bool reinvested;
  final String? userName;
}

typedef Payout = ({DateTime date, String? amount, bool overdue});
typedef RoadmapPoint = ({DateTime date, double amount, double cumulative, String status});

class InvestmentRoadmap {
  const InvestmentRoadmap({
    required this.end,
    required this.points,
    required this.totalReturn,
    this.breakEven,
    this.maturityValue,
    this.maturityGain,
    this.installmentsLeft = 0,
    this.installmentsTotal,
  });

  factory InvestmentRoadmap.fromJson(Json json) => InvestmentRoadmap(
    end: _date(json['end'])!,
    points: _list(
      json['points'],
      (point) => (
        date: _date(point['date'])!,
        amount: _number(point['amount']),
        cumulative: _number(point['cumulative']),
        status: point['status'] as String,
      ),
    ),
    totalReturn: json['total_return'].toString(),
    breakEven: _date(json['break_even']),
    maturityValue: json['maturity_value']?.toString(),
    maturityGain: json['maturity_gain']?.toString(),
    installmentsLeft: json['installments_left'] as int? ?? 0,
    installmentsTotal: json['installments_total']?.toString(),
  );

  final DateTime end;
  final List<RoadmapPoint> points;
  final String totalReturn;
  final DateTime? breakEven;
  final String? maturityValue;
  final String? maturityGain;
  final int installmentsLeft;
  final String? installmentsTotal;
}

typedef Contribution = ({String amount, String frequency, String? accountId, DateTime? nextDate});

typedef Contributor = ({String? name, String invested, double share, String income, String value});

class Investment {
  const Investment({
    required this.id,
    required this.name,
    required this.kind,
    required this.tracksUnits,
    required this.currency,
    required this.accountId,
    required this.units,
    required this.cost,
    required this.value,
    required this.gain,
    required this.invested,
    required this.income,
    required this.realized,
    required this.upcomingPayouts,
    required this.events,
    this.symbol,
    this.interestRate,
    this.payoutFrequency,
    this.startDate,
    this.maturityDate,
    this.closedAt,
    this.notes,
    this.price,
    this.gainPercent,
    this.payoutAmount,
    this.roadmap,
    this.taxRate,
    this.annualReturn,
    this.contribution,
    this.contributors = const [],
  });

  factory Investment.fromJson(Json json) => Investment(
    id: json['id'] as String,
    name: json['name'] as String,
    kind: json['kind'] as String,
    tracksUnits: json['tracks_units'] as bool? ?? false,
    currency: Currency.fromJson(json['currency'] as Json),
    accountId: json['account_id'] as String,
    symbol: json['symbol'] as String?,
    interestRate: double.tryParse(json['interest_rate']?.toString() ?? ''),
    payoutFrequency: json['payout_frequency'] as String?,
    startDate: _date(json['start_date']),
    maturityDate: _date(json['maturity_date']),
    closedAt: _date(json['closed_at']),
    notes: json['notes'] as String?,
    units: _number(json['units']),
    price: json['price']?.toString(),
    cost: json['cost'].toString(),
    value: json['value'].toString(),
    gain: json['gain'].toString(),
    gainPercent: double.tryParse(json['gain_percent']?.toString() ?? ''),
    invested: json['invested'].toString(),
    income: json['income'].toString(),
    realized: json['realized'].toString(),
    payoutAmount: json['payout_amount']?.toString(),
    upcomingPayouts: _list(
      json['upcoming_payouts'],
      (payout) => (date: _date(payout['date'])!, amount: payout['amount']?.toString(), overdue: payout['overdue'] as bool? ?? false),
    ),
    events: _list(json['events'], InvestmentEvent.fromJson),
    roadmap: json['roadmap'] is Map ? InvestmentRoadmap.fromJson((json['roadmap'] as Map).cast<String, dynamic>()) : null,
    taxRate: double.tryParse(json['tax_rate']?.toString() ?? ''),
    annualReturn: double.tryParse(json['annual_return']?.toString() ?? ''),
    contribution: json['contribution'] is Map
        ? (
            amount: json['contribution']['amount'].toString(),
            frequency: json['contribution']['frequency'] as String,
            accountId: json['contribution']['account_id'] as String?,
            nextDate: _date(json['contribution']['next_date']),
          )
        : null,
    contributors: _list(
      json['contributors'],
      (item) => (
        name: item['name'] as String?,
        invested: item['invested'].toString(),
        share: _number(item['share']),
        income: item['income'].toString(),
        value: item['value'].toString(),
      ),
    ),
  );

  final String id;
  final String name;
  final String kind;
  final bool tracksUnits;
  final Currency currency;
  final String accountId;
  final String? symbol;
  final double? interestRate;
  final String? payoutFrequency;
  final DateTime? startDate;
  final DateTime? maturityDate;
  final DateTime? closedAt;
  final String? notes;
  final double units;
  final String? price;
  final String cost;
  final String value;
  final String gain;
  final double? gainPercent;
  final String invested;
  final String income;
  final String realized;
  final String? payoutAmount;
  final List<Payout> upcomingPayouts;
  final List<InvestmentEvent> events;
  final InvestmentRoadmap? roadmap;
  final double? taxRate;
  final double? annualReturn;
  final Contribution? contribution;
  final List<Contributor> contributors;

  bool get held => _number(cost) > 0 || units > 0;
}

class Portfolio {
  const Portfolio({
    required this.investments,
    required this.currency,
    required this.value,
    required this.cost,
    required this.gain,
    required this.income,
    required this.allocation,
    this.gainPercent,
    this.annualReturn,
    this.targets = const [],
  });

  factory Portfolio.fromJson(Json json) {
    final summary = (json['summary'] as Map).cast<String, dynamic>();
    return Portfolio(
      investments: _list(json['data'], Investment.fromJson),
      currency: summary['currency'] as String,
      value: summary['value'].toString(),
      cost: summary['cost'].toString(),
      gain: summary['gain'].toString(),
      income: summary['income'].toString(),
      gainPercent: double.tryParse(summary['gain_percent']?.toString() ?? ''),
      allocation: _list(summary['allocation'], (item) => (kind: item['kind'] as String, value: _number(item['value']))),
      annualReturn: double.tryParse(summary['annual_return']?.toString() ?? ''),
      targets: _list(summary['targets'], parseTarget),
    );
  }

  final List<Investment> investments;
  final String currency;
  final String value;
  final String cost;
  final String gain;
  final String income;
  final double? gainPercent;
  final List<({String kind, double value})> allocation;
  final double? annualReturn;
  final List<InvestmentTarget> targets;
}

typedef InvestmentTarget = ({String kind, double percent});

InvestmentTarget parseTarget(Json json) => (kind: json['kind'] as String, percent: _number(json['percent']));

class Member {
  const Member({required this.id, required this.name});

  factory Member.fromJson(Json json) => Member(id: json['id'] as String, name: json['name'] as String);

  final String id;
  final String name;
}

typedef IncomeRow = ({String id, String name, String kind, String currency, String income, String realized});

class InvestmentIncome {
  const InvestmentIncome({
    required this.rows,
    required this.currency,
    required this.complete,
    required this.income,
    required this.realized,
  });

  factory InvestmentIncome.fromJson(Json json) => InvestmentIncome(
    rows: _list(
      json['rows'],
      (row) => (
        id: row['id'] as String,
        name: row['name'] as String,
        kind: row['kind'] as String,
        currency: row['currency'] as String,
        income: row['income'].toString(),
        realized: row['realized'].toString(),
      ),
    ),
    currency: json['currency'] as String,
    complete: json['complete'] as bool? ?? true,
    income: json['income'].toString(),
    realized: json['realized'].toString(),
  );

  final List<IncomeRow> rows;
  final String currency;
  final bool complete;
  final String income;
  final String realized;
}

class CalendarItem {
  const CalendarItem({
    required this.investmentId,
    required this.name,
    required this.kind,
    required this.currency,
    required this.type,
    required this.date,
    required this.overdue,
    this.amount,
  });

  factory CalendarItem.fromJson(Json json) => CalendarItem(
    investmentId: json['investment_id'] as String,
    name: json['name'] as String,
    kind: json['kind'] as String,
    currency: json['currency'] as String,
    type: json['type'] as String,
    date: _date(json['date'])!,
    amount: json['amount']?.toString(),
    overdue: json['overdue'] as bool? ?? false,
  );

  final String investmentId;
  final String name;
  final String kind;
  final String currency;
  final String type;
  final DateTime date;
  final String? amount;
  final bool overdue;
}

class Projection {
  const Projection({required this.roadmap, this.payoutAmount});

  factory Projection.fromJson(Json json) => Projection(
    payoutAmount: json['payout_amount']?.toString(),
    roadmap: InvestmentRoadmap.fromJson((json['roadmap'] as Map).cast<String, dynamic>()),
  );

  final String? payoutAmount;
  final InvestmentRoadmap roadmap;
}

typedef ZakatItem = ({String group, String name, String amount});

class Zakat {
  const Zakat({
    required this.date,
    required this.currency,
    required this.complete,
    required this.items,
    required this.total,
    required this.nisab,
    required this.nisabGoldGrams,
    required this.rate,
    required this.due,
    required this.zakat,
  });

  factory Zakat.fromJson(Json json) => Zakat(
    date: _date(json['date'])!,
    currency: json['currency'] as String,
    complete: json['complete'] as bool? ?? true,
    items: _list(
      json['items'],
      (item) => (group: item['group'] as String, name: item['name'] as String, amount: item['amount'].toString()),
    ),
    total: json['total'].toString(),
    nisab: json['nisab'].toString(),
    nisabGoldGrams: _number(json['nisab_gold_grams']),
    rate: _number(json['rate']),
    due: json['due'] as bool? ?? false,
    zakat: json['zakat'].toString(),
  );

  final DateTime date;
  final String currency;
  final bool complete;
  final List<ZakatItem> items;
  final String total;
  final String nisab;
  final double nisabGoldGrams;
  final double rate;
  final bool due;
  final String zakat;
}
