import 'package:dio/dio.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.status, this.errors = const {}});

  final String message;
  final int? status;
  final Map<String, List<String>> errors;

  bool get unauthorized => status == 401;

  String? field(String name) => errors[name]?.first;

  factory ApiException.from(Object error) {
    if (error is ApiException) return error;
    if (error is! DioException) return ApiException(error.toString());

    final response = error.response;
    if (response == null) {
      return ApiException('Could not reach the server. Check the address and your connection.');
    }

    final data = response.data;
    final errors = <String, List<String>>{};
    var message = 'Something went wrong (${response.statusCode}).';

    if (data is Map) {
      if (data['message'] is String) message = data['message'] as String;
      final raw = data['errors'];
      if (raw is Map) {
        raw.forEach((key, value) {
          if (value is List) errors[key.toString()] = value.map((e) => e.toString()).toList();
        });
        if (errors.isNotEmpty) message = errors.values.first.first;
      }
    }

    return ApiException(message, status: response.statusCode, errors: errors);
  }

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({required String baseUrl, String? token, void Function()? onUnauthorized})
    : _dio = Dio(
        BaseOptions(
          baseUrl: '${baseUrl.replaceAll(RegExp(r'/+$'), '')}/api/v1/',
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 30),
          listFormat: ListFormat.multiCompatible,
          headers: {'Accept': 'application/json', if (token != null) 'Authorization': 'Bearer $token'},
        ),
      ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onError: (error, handler) {
          if (error.response?.statusCode == 401 && token != null) onUnauthorized?.call();
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) => _send(() => _dio.get(path, queryParameters: _clean(query)));

  Future<dynamic> post(String path, [Object? body]) => _send(() => _dio.post(path, data: body));

  Future<dynamic> put(String path, [Object? body]) => _send(() => _dio.put(path, data: body));

  Future<dynamic> patch(String path, [Object? body]) => _send(() => _dio.patch(path, data: body));

  Future<dynamic> delete(String path) => _send(() => _dio.delete(path));

  Future<dynamic> _send(Future<Response<dynamic>> Function() request) async {
    try {
      return (await request()).data;
    } catch (error) {
      throw ApiException.from(error);
    }
  }

  Map<String, dynamic>? _clean(Map<String, dynamic>? query) {
    if (query == null) return null;
    return {
      for (final entry in query.entries)
        if (entry.value != null && entry.value != '') entry.key: entry.value,
    };
  }
}
