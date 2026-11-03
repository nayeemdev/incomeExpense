typedef Json = Map<String, dynamic>;

List<T> _list<T>(Object? raw, T Function(Json) parse) =>
    raw is List ? raw.whereType<Map>().map((item) => parse(item.cast<String, dynamic>())).toList() : <T>[];

DateTime? _date(Object? raw) => raw is String ? DateTime.tryParse(raw) : null;

class Currency {
  const Currency({required this.id, required this.code, required this.decimals});

  factory Currency.fromJson(Json json) =>
      Currency(id: json['id'] as int, code: json['code'] as String, decimals: json['decimal_places'] as int? ?? 2);

  final int id;
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
    id: json['id'] as int,
    name: json['name'] as String,
    email: json['email'] as String,
    twoFactor: json['two_factor_enabled'] as bool? ?? false,
  );

  final int id;
  final String name;
  final String email;
  final bool twoFactor;
}

class Ledger {
  const Ledger({required this.id, required this.name, required this.currency, required this.role, this.defaultAccountId});

  factory Ledger.fromJson(Json json) => Ledger(
    id: json['id'] as int,
    name: json['name'] as String,
    currency: Currency.fromJson(json['primary_currency'] as Json),
    role: json['role'] as String? ?? 'viewer',
    defaultAccountId: json['default_account_id'] as int?,
  );

  final int id;
  final String name;
  final Currency currency;
  final String role;
  final int? defaultAccountId;

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
    id: json['id'] as int,
    name: json['name'] as String,
    type: json['type'] as String,
    active: json['active'] as bool? ?? true,
    currency: json['currency'] is Map ? Currency.fromJson(json['currency'] as Json) : null,
    balance: json['balance']?.toString(),
    role: (json['asset_role'] ?? json['liability_type']) as String?,
    liabilityDirection: json['liability_direction'] as String?,
  );

  final int id;
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

  factory AccountRef.fromJson(Json json) => AccountRef(id: json['id'] as int, name: json['name'] as String, type: json['type'] as String);

  final int id;
  final String name;
  final String type;
}

/// A reference by id and name. Categories also carry their icon and color.
class NamedRef {
  const NamedRef(this.id, this.name, {this.icon, this.color});

  factory NamedRef.fromJson(Json json) =>
      NamedRef(json['id'] as int, json['name'] as String, icon: json['icon'] as String?, color: json['color'] as String?);

  final int id;
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
    id: json['id'] as int,
    groupId: json['group_id'] as int,
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

  final int id;
  final int groupId;
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

  factory TransactionGroup.fromJson(Json json) =>
      TransactionGroup(id: json['id'] as int, title: json['title'] as String?, splits: _list(json['transactions'], Transaction.fromJson));

  final int id;
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
    id: json['id'] as int,
    name: json['name'] as String,
    limits: _list(json['limits'], BudgetLimit.fromJson),
    spent: _list(json['spent'], Amount.fromJson),
  );

  final int id;
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
    id: json['id'] as int,
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

  final int id;
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

  final int? id;
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
        id: row['id'] as int?,
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
    id: json['id'] as int,
    name: json['name'] as String,
    currency: Currency.fromJson(json['currency'] as Json),
    saved: double.tryParse(json['saved_amount'].toString()) ?? 0,
    target: double.tryParse(json['target_amount']?.toString() ?? ''),
    targetDate: _date(json['target_date']),
    accounts: _list(json['accounts'], (account) => NamedRef(account['id'] as int, account['name'] as String)),
  );

  final int id;
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
        CategoryTotal(row['name'] as String?, Amount(currency, row['total'].toString()), id: row['id'] as int?);
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
