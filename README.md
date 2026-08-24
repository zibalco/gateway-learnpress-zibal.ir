# Zibal Payment Gateway for LearnPress

افزونه را فعال کنید

تنظیمات افزونه از طریق منوی «تسویه حساب» لرن پرس قابل دسترسی است.

نسخه 2.3.0 با PHP 5.6 تا 8.5 از نظر کد افزونه سازگار نگه داشته شده است. نسخهٔ PHP موردنیاز برای اجرای واقعی ممکن است به حداقل نسخهٔ WordPress و LearnPress نصب‌شده محدود شود.

در پرداخت‌های تومانی (`IRT`) مبلغ پیش از درخواست زیبال به ریال تبدیل می‌شود. `trackId` فقط از پاسخ خود زیبال دریافت و برای اتصال callback به همان پرداخت ذخیره می‌شود؛ افزونه trackId جداگانه تولید نمی‌کند.

هر دو درخواست `request` و `verify` این User-Agent قابل شناسایی را می‌فرستند:

```text
LearnPress-Zibal-Gateway/2.3.0 (WordPress; plugin=gateway-learnpress-zibal.ir; gateway=zibal)
```

وب‌سایت: https://www.zibal.ir
