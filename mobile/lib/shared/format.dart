import 'package:intl/intl.dart';

String formatMoney(String amount, String currency, {int? decimals, bool signed = false}) {
  final value = double.tryParse(amount) ?? 0;
  final format = NumberFormat.simpleCurrency(name: currency, decimalDigits: decimals);
  final text = format.format(value.abs());
  if (value < 0) return '-$text';
  return signed && value > 0 ? '+$text' : text;
}

String negate(String amount) => amount.startsWith('-') ? amount.substring(1) : '-$amount';

String formatDay(DateTime date) {
  final today = Dates.today();
  final difference = Dates.dateOnly(date).difference(today).inDays;
  if (difference == 0) return 'Today';
  if (difference == -1) return 'Yesterday';
  if (difference == 1) return 'Tomorrow';
  return DateFormat(date.year == today.year ? 'EEE, d MMM' : 'd MMM y').format(date);
}

String formatDate(DateTime date) => DateFormat('d MMM y').format(date);

String isoDate(DateTime date) => DateFormat('yyyy-MM-dd').format(date);

String sentenceCase(String value) {
  final words = value.replaceAll('_', ' ');
  return words.isEmpty ? words : words[0].toUpperCase() + words.substring(1);
}

abstract final class Dates {
  static DateTime dateOnly(DateTime date) => DateTime(date.year, date.month, date.day);

  static DateTime today() => dateOnly(DateTime.now());

  static (DateTime, DateTime) thisMonth() {
    final now = DateTime.now();
    return (DateTime(now.year, now.month), DateTime(now.year, now.month + 1, 0));
  }
}
