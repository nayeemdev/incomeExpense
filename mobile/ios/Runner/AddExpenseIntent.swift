import AppIntents
import UIKit

/// "Add an expense in LedgerFly" from Siri, Spotlight, Shortcuts or the Action Button.
/// It opens the app on the quick add screen with whatever was said already filled in.
@available(iOS 16.0, *)
struct AddExpenseIntent: AppIntent {
    static let title: LocalizedStringResource = "Add expense"
    static let description = IntentDescription("Open LedgerFly with a new expense filled in.")
    static let openAppWhenRun = true

    @Parameter(title: "Amount")
    var amount: Double?

    @Parameter(title: "What for")
    var note: String?

    @MainActor
    func perform() async throws -> some IntentResult {
        var components = URLComponents(string: "ledgerfly://app/new")!
        components.queryItems = [URLQueryItem(name: "type", value: "withdrawal")]
        if let amount, amount > 0 {
            components.queryItems?.append(URLQueryItem(name: "amount", value: String(format: "%.2f", amount)))
        }
        if let note, !note.isEmpty {
            components.queryItems?.append(URLQueryItem(name: "title", value: note))
        }
        if let url = components.url {
            await UIApplication.shared.open(url)
        }
        return .result()
    }
}

@available(iOS 16.0, *)
struct LedgerFlyShortcuts: AppShortcutsProvider {
    static var appShortcuts: [AppShortcut] {
        AppShortcut(
            intent: AddExpenseIntent(),
            phrases: [
                "Add an expense in \(.applicationName)",
                "Log spending in \(.applicationName)",
                "New \(.applicationName) expense",
            ],
            shortTitle: "Add expense",
            systemImageName: "plus.circle"
        )
    }
}
