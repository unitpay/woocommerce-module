=== Unitpay payment gateway for Woocommerce ===
Contributors: Unitpay
Tags: ecommerce payment, gateway, unitpay, woocommerce
Donate link: https://unitpay.ru
Requires at least: 4.0
Tested up to: 5.6
Requires PHP: >5.6
Stable tag: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Это официальный модуль Unitpay, позволяет добавить в ваш магазин WooCommerce оплату через платежный сервис [Unitpay](https://unitpay.ru/). Поддерживает интеграцию чеков (закон 54-ФЗ).

== Description ==
Данный плагин позволяет принимать платежи в Woocommerce через платежный шлюз Unitpay.

## Возможности:
* Выбор платежной системы и формирование заказа
* Поддержка "Юнит.Чеков". Передача состава товаров в заказе для отправки чека (ФЗ-54);
* Выбор НДС внутр имодуля
* Отдельный параметр НДС для доставки.

Поддержка https://unitpay.ru

== Installation ==
1. Скопируйте содержимое директории unitpay в архиве в директорию /<корень сайта>/wp-content/plugins/.
2. Зайдите в "Плагины"-> "Установленные" и нажмите "Активировать" напротив плагина UnitPay.
3. Выберите в меню "WooCommerce" -> "Настройки" и перейдите на вкладку "Платежи", там выберите UnitPay.
4. В поле DOMAIN вставьте значение unitpay.ru. В поля PUBLIC KEY и SECRET KEY скопируйте публичный и секретный ключ, которые вы можете взять из личного кабинета Unitpay. Нажмите на кнопку "Сохранить изменения".
5. Введите в личном кабинете Unitpay.ru обработчик платежей по шаблону  http(s)://your-domain.ru/?wc-api=wc_unitpay

== Changelog ==

== Screenshots ==

== Upgrade Notice ==