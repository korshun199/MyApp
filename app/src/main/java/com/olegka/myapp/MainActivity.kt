package com.olegka.myapp

import android.os.Bundle
import android.app.Activity
import android.widget.TextView

class MainActivity : Activity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Создаем текстовое поле на экране программно
        val textView = TextView(this)
        textView.text = "Привет, Олежка! Наше приложение работает!"
        textView.textSize = 24f
        
        // Выводим этот текст на экран телефона
        setContentView(textView)
    }
}
