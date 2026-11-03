import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../api/api_client.dart';

const _serverKey = 'server_url';
const _tokenKey = 'token';

final storageProvider = Provider((ref) => const FlutterSecureStorage());

class Session {
  const Session({required this.serverUrl, required this.token});

  final String serverUrl;
  final String token;
}

class TwoFactorRequired implements Exception {}

final sessionProvider = AsyncNotifierProvider<SessionNotifier, Session?>(SessionNotifier.new);

class SessionNotifier extends AsyncNotifier<Session?> {
  FlutterSecureStorage get _storage => ref.read(storageProvider);

  @override
  Future<Session?> build() async {
    final server = await _storage.read(key: _serverKey);
    final token = await _storage.read(key: _tokenKey);
    if (server == null || token == null) return null;
    return Session(serverUrl: server, token: token);
  }

  Future<String?> lastServer() => _storage.read(key: _serverKey);

  Future<void> signIn({required String server, required String email, required String password, String? code, String? recoveryCode}) async {
    final client = ApiClient(baseUrl: server);
    final dynamic response;
    try {
      response = await client.post('auth/tokens', {
        'email': email,
        'password': password,
        'device_name': 'LedgerFly on ${Platform.operatingSystem}',
        'code': ?code,
        'recovery_code': ?recoveryCode,
      });
    } on ApiException catch (error) {
      if (code == null && recoveryCode == null && error.field('code') != null) throw TwoFactorRequired();
      rethrow;
    }

    final token = response['token'] as String;
    await _storage.write(key: _serverKey, value: server);
    await _storage.write(key: _tokenKey, value: token);
    state = AsyncData(Session(serverUrl: server, token: token));
  }

  Future<void> signOut() async {
    final session = state.value;
    if (session != null) {
      try {
        await ApiClient(baseUrl: session.serverUrl, token: session.token).delete('auth/tokens/current');
      } on ApiException {
        // The token is dropped locally either way; the server may already have revoked it.
      }
    }
    await expire();
  }

  Future<void> expire() async {
    await _storage.delete(key: _tokenKey);
    state = const AsyncData(null);
  }
}

final apiProvider = Provider<ApiClient>((ref) {
  final session = ref.watch(sessionProvider).value;
  if (session == null) throw StateError('Not signed in');
  return ApiClient(baseUrl: session.serverUrl, token: session.token, onUnauthorized: () => ref.read(sessionProvider.notifier).expire());
});
