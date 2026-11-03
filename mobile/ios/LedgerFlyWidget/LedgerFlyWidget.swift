import SwiftUI
import WidgetKit

private let appGroup = "group.com.nayeemdev.ledgerfly"
private let addURL = URL(string: "ledgerfly://app/new?type=withdrawal")!

/// The numbers the Flutter app last wrote to the shared App Group.
struct Snapshot {
    var signedIn: Bool
    var ledger: String
    var spent: String
    var earned: String
    var budgetLeft: String?
    var budgetProgress: Double?
    var billName: String?
    var billAmount: String?
    var billDue: String?

    static func load() -> Snapshot {
        let store = UserDefaults(suiteName: appGroup)
        return Snapshot(
            signedIn: store?.bool(forKey: "signed_in") ?? false,
            ledger: store?.string(forKey: "ledger") ?? "LedgerFly",
            spent: store?.string(forKey: "spent") ?? "",
            earned: store?.string(forKey: "earned") ?? "",
            budgetLeft: store?.string(forKey: "budget_left"),
            budgetProgress: store?.object(forKey: "budget_progress") as? Double,
            billName: store?.string(forKey: "bill_name"),
            billAmount: store?.string(forKey: "bill_amount"),
            billDue: store?.string(forKey: "bill_due")
        )
    }

    static let preview = Snapshot(
        signedIn: true, ledger: "Household", spent: "$1,284.50", earned: "$4,250.00", budgetLeft: "$615.50",
        budgetProgress: 0.68, billName: "Internet", billAmount: "$59.99", billDue: "Tomorrow"
    )
}

struct Entry: TimelineEntry {
    let date: Date
    let snapshot: Snapshot
}

struct Provider: TimelineProvider {
    func placeholder(in context: Context) -> Entry { Entry(date: .now, snapshot: .preview) }

    func getSnapshot(in context: Context, completion: @escaping (Entry) -> Void) {
        completion(Entry(date: .now, snapshot: context.isPreview ? .preview : .load()))
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<Entry>) -> Void) {
        // The app pushes fresh numbers whenever it syncs; the hourly reload only refreshes relative dates.
        let entry = Entry(date: .now, snapshot: .load())
        completion(Timeline(entries: [entry], policy: .after(.now.addingTimeInterval(3600))))
    }
}

extension Color {
    static let brand = Color(light: 0x0076A8, dark: 0x2F9DCA)
    static let ink = Color(light: 0x0F2942, dark: 0xE6EEF5)
    static let muted = Color(light: 0x4A6580, dark: 0x8DA4B5)
    static let track = Color(light: 0xE1E9F0, dark: 0x15304B)
    static let expense = Color(light: 0xC0261F, dark: 0xF68D66)
    static let card = Color(light: 0xFFFFFF, dark: 0x0D2238)

    init(light: UInt32, dark: UInt32) {
        self.init(UIColor { traits in
            let hex = traits.userInterfaceStyle == .dark ? dark : light
            return UIColor(
                red: CGFloat((hex >> 16) & 0xFF) / 255, green: CGFloat((hex >> 8) & 0xFF) / 255,
                blue: CGFloat(hex & 0xFF) / 255, alpha: 1)
        })
    }
}

struct AddButton: View {
    var body: some View {
        Link(destination: addURL) {
            Image(systemName: "plus")
                .font(.system(size: 15, weight: .bold))
                .foregroundStyle(.white)
                .frame(width: 30, height: 30)
                .background(Circle().fill(Color.brand))
        }
        .accessibilityLabel("Add expense")
    }
}

struct BudgetBar: View {
    let progress: Double

    var body: some View {
        GeometryReader { proxy in
            ZStack(alignment: .leading) {
                Capsule().fill(Color.track)
                Capsule().fill(progress > 0.9 ? Color.expense : Color.brand).frame(width: proxy.size.width * progress)
            }
        }
        .frame(height: 6)
    }
}

struct SignedOutView: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Image(systemName: "lock.fill").foregroundStyle(Color.muted)
            Text("Open LedgerFly to sign in").font(.footnote.weight(.medium)).foregroundStyle(Color.ink)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .leading)
    }
}

struct SmallView: View {
    let snapshot: Snapshot

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack {
                Text("Spent").font(.caption.weight(.medium)).foregroundStyle(Color.muted)
                Spacer()
                AddButton()
            }
            Spacer(minLength: 4)
            Text(snapshot.spent)
                .font(.system(size: 24, weight: .bold, design: .rounded))
                .foregroundStyle(Color.ink)
                .minimumScaleFactor(0.5)
                .lineLimit(1)
            Text("this month").font(.caption2).foregroundStyle(Color.muted)
            Spacer(minLength: 6)
            if let progress = snapshot.budgetProgress, let left = snapshot.budgetLeft {
                BudgetBar(progress: progress)
                Text("\(left) left").font(.caption2.weight(.medium)).foregroundStyle(Color.muted).padding(.top, 4)
            }
        }
    }
}

struct MediumView: View {
    let snapshot: Snapshot

    var body: some View {
        HStack(spacing: 16) {
            SmallView(snapshot: snapshot)
            Rectangle().fill(Color.track).frame(width: 1)
            VStack(alignment: .leading, spacing: 10) {
                VStack(alignment: .leading, spacing: 2) {
                    Text("Earned").font(.caption.weight(.medium)).foregroundStyle(Color.muted)
                    Text(snapshot.earned).font(.system(.headline, design: .rounded)).foregroundStyle(Color.ink).lineLimit(1)
                }
                VStack(alignment: .leading, spacing: 2) {
                    Text("Next bill").font(.caption.weight(.medium)).foregroundStyle(Color.muted)
                    if let name = snapshot.billName {
                        Text(name).font(.subheadline.weight(.semibold)).foregroundStyle(Color.ink).lineLimit(1)
                        Text([snapshot.billAmount, snapshot.billDue].compactMap { $0 }.joined(separator: " · "))
                            .font(.caption).foregroundStyle(Color.expense).lineLimit(1)
                    } else {
                        Text("All paid").font(.subheadline.weight(.semibold)).foregroundStyle(Color.ink)
                    }
                }
                Spacer(minLength: 0)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
    }
}

struct WidgetView: View {
    @Environment(\.widgetFamily) private var family
    let entry: Entry

    var body: some View {
        let snapshot = entry.snapshot
        Group {
            if !snapshot.signedIn {
                switch family {
                case .accessoryInline: Text("LedgerFly")
                case .accessoryCircular, .accessoryRectangular: Image(systemName: "lock.fill")
                default: SignedOutView()
                }
            } else {
                switch family {
                case .systemMedium:
                    MediumView(snapshot: snapshot)
                case .accessoryRectangular:
                    VStack(alignment: .leading, spacing: 1) {
                        Text("Spent this month").font(.caption2).widgetAccentable()
                        Text(snapshot.spent).font(.headline).minimumScaleFactor(0.6)
                        if let left = snapshot.budgetLeft { Text("\(left) budget left").font(.caption2) }
                    }
                case .accessoryCircular:
                    Gauge(value: snapshot.budgetProgress ?? 0) {
                        Image(systemName: "chart.pie.fill")
                    } currentValueLabel: {
                        Text("\(Int(((snapshot.budgetProgress ?? 0) * 100).rounded()))%")
                    }
                    .gaugeStyle(.accessoryCircular)
                case .accessoryInline:
                    Text("Spent \(snapshot.spent)")
                default:
                    SmallView(snapshot: snapshot)
                }
            }
        }
        .widgetURL(URL(string: "ledgerfly://app/"))
        .containerBackground(for: .widget) { Color.card }
    }
}

@main
struct LedgerFlyWidget: Widget {
    var body: some WidgetConfiguration {
        StaticConfiguration(kind: "LedgerFlyWidget", provider: Provider()) { entry in
            WidgetView(entry: entry)
        }
        .configurationDisplayName("This month")
        .description("What you have spent, the budget left and the next bill due.")
        .supportedFamilies([.systemSmall, .systemMedium, .accessoryRectangular, .accessoryCircular, .accessoryInline])
    }
}

#Preview(as: .systemMedium) {
    LedgerFlyWidget()
} timeline: {
    Entry(date: .now, snapshot: .preview)
}
