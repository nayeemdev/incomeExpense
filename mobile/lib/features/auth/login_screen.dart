import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../api/api_client.dart';
import '../../shared/widgets.dart';
import 'session.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _server = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _code = TextEditingController();
  bool _needsCode = false;
  bool _useRecovery = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    ref.read(sessionProvider.notifier).lastServer().then((value) {
      if (value != null && mounted && _server.text.isEmpty) _server.text = value;
    });
  }

  @override
  void dispose() {
    for (final controller in [_server, _email, _password, _code]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final code = _code.text.trim();
    try {
      await ref.read(sessionProvider.notifier).signIn(
            server: _normalizedServer(),
            email: _email.text.trim(),
            password: _password.text,
            code: _needsCode && !_useRecovery ? code : null,
            recoveryCode: _needsCode && _useRecovery ? code : null,
          );
    } on TwoFactorRequired {
      setState(() => _needsCode = true);
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _normalizedServer() {
    final value = _server.text.trim();
    return value.contains('://') ? value : 'https://$value';
  }

  String? _required(String? value) => (value == null || value.trim().isEmpty) ? 'Required' : null;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Image.asset('assets/logo.png', height: 56),
                    const SizedBox(height: 16),
                    Text(_needsCode ? 'Two-factor code' : 'Sign in to LedgerFly',
                        style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600), textAlign: TextAlign.center),
                    const SizedBox(height: 4),
                    Text(
                      _needsCode
                          ? (_useRecovery ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.')
                          : 'Use the address of your LedgerFly server.',
                      style: TextStyle(color: theme.colorScheme.onSurfaceVariant),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 28),
                    if (!_needsCode) ...[
                      const FieldLabel('Server', required: true),
                      TextFormField(
                        controller: _server,
                        keyboardType: TextInputType.url,
                        autocorrect: false,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(hintText: 'ledger.example.com'),
                        validator: _required,
                      ),
                      const SizedBox(height: 16),
                      const FieldLabel('Email', required: true),
                      TextFormField(
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        autocorrect: false,
                        autofillHints: const [AutofillHints.email],
                        textInputAction: TextInputAction.next,
                        validator: _required,
                      ),
                      const SizedBox(height: 16),
                      const FieldLabel('Password', required: true),
                      TextFormField(
                        controller: _password,
                        obscureText: true,
                        autofillHints: const [AutofillHints.password],
                        onFieldSubmitted: (_) => _submit(),
                        validator: _required,
                      ),
                    ] else ...[
                      FieldLabel(_useRecovery ? 'Recovery code' : 'Code', required: true),
                      TextFormField(
                        controller: _code,
                        autofocus: true,
                        autocorrect: false,
                        keyboardType: _useRecovery ? TextInputType.text : TextInputType.number,
                        autofillHints: const [AutofillHints.oneTimeCode],
                        onFieldSubmitted: (_) => _submit(),
                        validator: _required,
                      ),
                      TextButton(
                        onPressed: () => setState(() {
                          _useRecovery = !_useRecovery;
                          _code.clear();
                        }),
                        child: Text(_useRecovery ? 'Use an authenticator code' : 'Use a recovery code'),
                      ),
                    ],
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy
                          ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                          : Text(_needsCode ? 'Verify' : 'Sign in'),
                    ),
                    if (_needsCode)
                      TextButton(
                        onPressed: () => setState(() {
                          _needsCode = false;
                          _code.clear();
                          _error = null;
                        }),
                        child: const Text('Back'),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
