package com.apptimatic.app;

import android.animation.Animator;
import android.animation.AnimatorListenerAdapter;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.ViewGroup;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import android.widget.FrameLayout;
import android.widget.ImageView;
import androidx.core.splashscreen.SplashScreen;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private static final String SPLASH_BG_COLOR = "#302B87";
    private static final long MIN_SPLASH_TIME = 1300;
    private static final long MAX_SPLASH_TIME = 4500;

    private ImageView splashOverlay;
    private boolean keepSystemSplash = true;
    private boolean isDismissed = false;
    private boolean pendingDismiss = false;
    private long startTime;
    private final Handler handler = new Handler(Looper.getMainLooper());

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        startTime = System.currentTimeMillis();
        SplashScreen splashScreen = SplashScreen.installSplashScreen(this);
        splashScreen.setKeepOnScreenCondition(() -> keepSystemSplash);

        super.onCreate(savedInstanceState);

        setupNativeSplash();
    }

    private void setupNativeSplash() {
        ViewGroup root = findViewById(android.R.id.content);
        if (root != null) {
            root.setBackgroundColor(Color.parseColor(SPLASH_BG_COLOR));

            splashOverlay = new ImageView(this);
            splashOverlay.setImageResource(R.drawable.splash);
            splashOverlay.setScaleType(ImageView.ScaleType.CENTER_CROP);
            splashOverlay.setBackgroundColor(Color.parseColor(SPLASH_BG_COLOR));

            FrameLayout.LayoutParams params = new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            );
            root.addView(splashOverlay, params);
        }

        if (bridge != null && bridge.getWebView() != null) {
            WebView webView = bridge.getWebView();
            webView.setBackgroundColor(Color.parseColor(SPLASH_BG_COLOR));
            webView.addJavascriptInterface(new Object() {
                @JavascriptInterface
                public void hideSplash() {
                    requestDismiss();
                }
            }, "NativeSplashBridge");
        }

        // Hard fallback to guarantee splash screen always dismisses
        handler.postDelayed(this::forceDismiss, MAX_SPLASH_TIME);
    }

    public void requestDismiss() {
        handler.post(() -> {
            long elapsed = System.currentTimeMillis() - startTime;
            if (elapsed < MIN_SPLASH_TIME) {
                if (!pendingDismiss) {
                    pendingDismiss = true;
                    handler.postDelayed(this::forceDismiss, MIN_SPLASH_TIME - elapsed);
                }
            } else {
                forceDismiss();
            }
        });
    }

    private void forceDismiss() {
        if (isDismissed) return;
        isDismissed = true;
        keepSystemSplash = false;

        if (splashOverlay != null) {
            splashOverlay.animate()
                .alpha(0f)
                .setDuration(350)
                .setListener(new AnimatorListenerAdapter() {
                    @Override
                    public void onAnimationEnd(Animator animation) {
                        if (splashOverlay != null && splashOverlay.getParent() != null) {
                            ((ViewGroup) splashOverlay.getParent()).removeView(splashOverlay);
                            splashOverlay = null;
                        }
                    }
                });
        }
    }

    @Override
    public void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }
}
