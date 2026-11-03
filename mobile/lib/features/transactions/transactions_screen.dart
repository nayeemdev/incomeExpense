import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../shared/widgets.dart';
import 'transaction_list.dart';

class TransactionsScreen extends StatefulWidget {
  const TransactionsScreen({super.key});

  @override
  State<TransactionsScreen> createState() => _TransactionsScreenState();
}

class _TransactionsScreenState extends State<TransactionsScreen> {
  static const _types = {'': 'All', 'withdrawal': 'Expenses', 'deposit': 'Income', 'transfer': 'Transfers'};

  final _search = TextEditingController();
  Timer? _debounce;
  String _query = '';
  String _type = '';

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () => setState(() => _query = value.trim()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: TransactionList(
          filter: (query: _query, type: _type.isEmpty ? null : _type, accountId: null),
          header: [
            const PageHeader(title: 'Transactions', description: 'Every expense, income and transfer. Search to narrow it down.'),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                controller: _search,
                onChanged: _onSearch,
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  prefixIcon: const Icon(LucideIcons.search, size: 18),
                  hintText: 'Search, e.g. coffee or category:Groceries',
                  suffixIcon: _search.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(LucideIcons.x, size: 18),
                          onPressed: () {
                            _search.clear();
                            setState(() => _query = '');
                          },
                        ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Segmented<String>(options: _types, value: _type, onChanged: (type) => setState(() => _type = type)),
            ),
          ],
        ),
      ),
    );
  }
}
