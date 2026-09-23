# wpp-email-editor

Визуальный редактор писем для WooCommerce. Автор — **WP Panda**.

Плагин заменяет стандартные транзакционные письма магазином, который вы собираете из блоков: шапка, текст, кнопка, состав заказа, адреса, купон, подвал. Шаблоны темы трогать не нужно. Пока макет не включён, WooCommerce отправляет своё письмо как раньше.

## Что умеет

- Отдельный макет на каждое письмо WooCommerce, включая письма других плагинов.
- Холст с перетаскиванием, отменой, дублем и превью на компьютере и телефоне.
- Метки WooCommerce: `{order_number}`, `{customer_first_name}`, `{order_url}` и другие.
- Фирменный стиль: логотип, цвета, шрифт, подвал, соцсети.
- Превью на примере или на реальном заказе, тестовое письмо, версии, импорт и экспорт.
- Письмо уходит вместо шаблона WooCommerce только если макет включён переключателем «Использовать этот макет».
- Совместимость с HPOS. Русский интерфейс, если язык сайта русский, или принудительно из фирменного стиля.

## Требования

- WordPress 6.2+
- PHP 7.4+
- WooCommerce 7.1+

## Установка

1. Скопируйте эту папку в `wp-content/plugins/wpp-email-editor`.
2. В админке включите **WPP Email Editor**.
3. Откройте **WooCommerce → Email Editor** или кнопку «Оформить это письмо» в настройках конкретного письма.

Сборка не нужна: редактор — обычные CSS и JavaScript.

## Как устроена отправка

Когда письмо включено, плагин подменяет тело в `woocommerce_mail_callback_params` (и запасным фильтром `wp_mail`, если версия WooCommerce этот хук не отдаёт). Тема берётся из фильтра `woocommerce_email_subject_{id}`. Пустой макет и выключенный переключатель не трогают стандартный шаблон.

Если в WooCommerce выбран текстовый формат, уходит текстовая версия тех же блоков.

## Блоки

Шапка, заголовок, текст, изображение, кнопка, колонки, разделитель, отступ, сводка заказа, состав заказа, адреса, загрузки, заметка, действие с аккаунтом, склад, товары, купон, соцсети, дополнительный текст WooCommerce, свой HTML, подвал.

Вокруг таблицы заказа по-прежнему вызываются `woocommerce_email_before_order_table`, `woocommerce_email_after_order_table` и `woocommerce_email_order_meta`, чтобы другие плагины могли добавить свою строку.

## Хуки

```php
apply_filters( 'wpp_ee_design', $design, $email_id, $context );
apply_filters( 'wpp_ee_rendered_html', $html, $email_id, $context, $design );
apply_filters( 'wpp_ee_block_html', $html, $block, $context, $settings );
apply_filters( 'wpp_ee_placeholder_map', $map, $context );
apply_filters( 'wpp_ee_blueprint', $design, $email_id, $brand );
apply_filters( 'wpp_ee_capability', 'manage_woocommerce' );
do_action( 'wpp_ee_after_save', $email_id, $design );
```

Своя метка:

```php
add_filter( 'wpp_ee_placeholder_map', function ( $map, $context ) {
    $map['{pickup_code}'] = 'A-14';
    return $map;
}, 10, 2 );
```

## Демо редактора

В папке `demo/` лежит тот же интерфейс без WordPress — чтобы посмотреть холст. Отправка писем там не работает: для этого нужен сайт с WooCommerce.

## Лицензия

GPL-2.0-or-later.
