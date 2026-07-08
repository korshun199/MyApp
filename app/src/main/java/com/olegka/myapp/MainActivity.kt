package com.olegka.myapp

import android.os.Bundle
import android.app.Activity
import android.content.Intent
import android.net.Uri
import android.webkit.ValueCallback
import android.webkit.WebView
import android.webkit.WebViewClient
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.EditText
import android.widget.Button
import android.util.Log
import java.io.ByteArrayInputStream

class MainActivity : Activity() {
    private lateinit var webView: WebView
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private val FILE_CHOOSER_RESULT_CODE = 1

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        val mainLayout = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            layoutParams = ViewGroup.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
        }

        val topBar = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutParams = LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            )
        }

        val urlInput = EditText(this).apply {
            layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f)
            hint = "Введите адрес..."
            setText("https://systemio.ru/browser/index.html")
        }

        val goButton = Button(this).apply {
            text = "Go"
            setOnClickListener {
                var url = urlInput.text.toString().trim()
                if (url.isNotEmpty()) {
                    if (!url.startsWith("http://") && !url.startsWith("https://")) {
                        if (url.contains(".") && !url.contains(" ")) {
                            url = "https://$url"
                        } else {
                            url = "https://www.google.com/search?q=" + java.net.URLEncoder.encode(url, "UTF-8")
                        }
                    }
                    webView.loadUrl(url)
                }
            }
        }

        topBar.addView(urlInput)
        topBar.addView(goButton)
        mainLayout.addView(topBar)

        webView = WebView(this).apply {
            layoutParams = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT)
        }
        mainLayout.addView(webView)
        setContentView(mainLayout)

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            useWideViewPort = true
            loadWithOverviewMode = true
            allowFileAccess = true // Разрешаем доступ к файлам
            mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
            userAgentString = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        }

        // Включаем поддержку скачивания файлов (например, APK) через системный браузер/загрузчик
        webView.setDownloadListener { url, _, _, _, _ ->
            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
            startActivity(intent)
        }

        // ЮЛИЧКА ДЛЯ ОЛЕЖКИ: Перехватываем нажатие кнопки выбора файла на сайте!
        webView.webChromeClient = object : WebChromeClient() {
            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                this@MainActivity.filePathCallback?.onReceiveValue(null)
                this@MainActivity.filePathCallback = filePathCallback

                val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    type = "*/*" // Разрешаем выбирать любые типы файлов для загрузки
                }
                startActivityForResult(Intent.createChooser(intent, "Выберите файл"), FILE_CHOOSER_RESULT_CODE)
                return true
            }
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                if (url != null) view?.loadUrl(url)
                return true
            }

            override fun shouldInterceptRequest(view: WebView?, request: WebResourceRequest?): WebResourceResponse? {
                val url = request?.url?.toString() ?: return null
                if (url.contains("ads") || url.contains("doubleclick") || url.contains("adservice") || url.contains("analytics")) {
                    return WebResourceResponse("text/plain", "UTF-8", ByteArrayInputStream("".toByteArray()))
                }
                return super.shouldInterceptRequest(view, request)
            }
        }

        webView.loadUrl("https://systemio.ru/browser/index.html")
    }

    // Получаем выбранный файл из системы и передаем его обратно в WebView
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        if (requestCode == FILE_CHOOSER_RESULT_CODE) {
            if (filePathCallback == null) return
            val results = if (resultCode == Activity.RESULT_OK && data != null) {
                val dataString = data.dataString
                if (dataString != null) arrayOf(Uri.parse(dataString)) else null
            } else null
            filePathCallback?.onReceiveValue(results)
            filePathCallback = null
        } else {
            super.onActivityResult(requestCode, resultCode, data)
        }
    }

    override fun onBackPressed() {
        if (webView.canGoBack()) webView.goBack() else super.onBackPressed()
    }
}
