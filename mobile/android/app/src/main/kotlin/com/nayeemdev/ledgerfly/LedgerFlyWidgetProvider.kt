package com.nayeemdev.ledgerfly

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.net.Uri
import android.os.Build
import android.util.SizeF
import android.view.View
import android.widget.RemoteViews
import es.antonborri.home_widget.HomeWidgetProvider

/** Shows this month's spending, the budget left and the next bill, from what the Flutter app last saved. */
class LedgerFlyWidgetProvider : HomeWidgetProvider() {
    override fun onUpdate(
        context: Context,
        appWidgetManager: AppWidgetManager,
        appWidgetIds: IntArray,
        widgetData: SharedPreferences,
    ) {
        val small = build(context, widgetData, R.layout.widget_small)
        val medium = build(context, widgetData, R.layout.widget_medium)
        val views =
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                RemoteViews(mapOf(SizeF(110f, 110f) to small, SizeF(250f, 110f) to medium))
            } else {
                medium
            }
        appWidgetIds.forEach { appWidgetManager.updateAppWidget(it, views) }
    }

    private fun build(context: Context, data: SharedPreferences, layout: Int): RemoteViews {
        val views = RemoteViews(context.packageName, layout)
        views.setOnClickPendingIntent(R.id.widget_root, link(context, "ledgerfly://app/", 0))
        views.setOnClickPendingIntent(R.id.widget_add, link(context, "ledgerfly://app/new?type=withdrawal", 1))

        val signedIn = data.getBoolean("signed_in", false)
        views.setViewVisibility(R.id.widget_content, if (signedIn) View.VISIBLE else View.GONE)
        views.setViewVisibility(R.id.widget_signed_out, if (signedIn) View.GONE else View.VISIBLE)
        if (!signedIn) return views

        views.setTextViewText(R.id.widget_spent, data.getString("spent", ""))
        val left = data.getString("budget_left", null)
        val progress = readDouble(data, "budget_progress")
        val hasBudget = left != null && progress != null
        views.setViewVisibility(R.id.widget_budget, if (hasBudget) View.VISIBLE else View.GONE)
        if (hasBudget) {
            views.setProgressBar(R.id.widget_budget_bar, 100, (progress!! * 100).toInt(), false)
            views.setTextViewText(R.id.widget_budget_left, context.getString(R.string.widget_left, left))
        }

        if (layout == R.layout.widget_medium) {
            views.setTextViewText(R.id.widget_earned, data.getString("earned", ""))
            val bill = data.getString("bill_name", null)
            views.setTextViewText(R.id.widget_bill_name, bill ?: context.getString(R.string.widget_all_paid))
            val detail = listOfNotNull(data.getString("bill_amount", null), data.getString("bill_due", null)).joinToString(" · ")
            views.setTextViewText(R.id.widget_bill_detail, detail)
            views.setViewVisibility(R.id.widget_bill_detail, if (bill == null) View.GONE else View.VISIBLE)
        }
        return views
    }

    private fun link(context: Context, uri: String, requestCode: Int): PendingIntent {
        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(uri), context, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
        return PendingIntent.getActivity(context, requestCode, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
    }

    // home_widget stores doubles as their raw bits in a Long.
    private fun readDouble(data: SharedPreferences, key: String): Double? =
        if (data.contains(key)) java.lang.Double.longBitsToDouble(data.getLong(key, 0)) else null
}
