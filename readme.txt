=== Zibal Payment Gateway for Learnpress ===
Contributors: zibal team
Tags: learnpress,zibal,gateway,payment,زیبال,lms,لرن پرس
Requires at least: 5.7
Tested up to: 6.4
Requires PHP: 5.6
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

با نصب این پلاگین می توانید از خدمات درگاه پرداخت واسط و مستقیم و یا اختصاصی زیبال برروی افزونه لرن پرس استفاده کنید!

== Description ==
 افزونه Zibal Payment Gateway for Learnpress امکان فروش اینترنتی و آنلاین از طریق درگاه پرداخت زیبال به افزونه مدیریت آموزش الکترونیک لرن پرس اضافه می کند.


== Installation ==

افزونه را فعال کنید

تنظیمات افزونه از طریق منوی تسویه حساب لرن پرس قابل دسترسی می باشد.

== Compatibility ==

کد افزونه برای PHP 5.6 تا PHP 8.5 نوشته شده است. حداقل PHP قابل استفاده در عمل به نسخه وردپرس و LearnPress نصب شده نیز وابسته است؛ نسخه‌های جدید LearnPress ممکن است PHP جدیدتری نیاز داشته باشند.

مبالغ IRT پیش از ارسال به زیبال به ریال تبدیل می‌شوند و مبلغ‌های IRR بدون ضریب ارسال می‌شوند.

درخواست‌های request و verify هدر User-Agent زیر را ارسال می‌کنند:

`LearnPress-Zibal-Gateway/2.3.0 (WordPress; plugin=gateway-learnpress-zibal.ir; gateway=zibal)`

== Changelog ==

= 2.3.0 =
* تبدیل امن مبلغ تومان به ریال و ثبت واحد مبلغ پرداخت.
* جلوگیری از تغییر وضعیت سفارش توسط callback نامعتبر.
* حفظ وضعیت قابل بازیابی در خطاهای موقت verify.
* اصلاح کد already-verified زیبال از 101 به 201.
* یکسان‌سازی نسخه و User-Agent و افزودن تست‌های سازگار با PHP 5.6.
