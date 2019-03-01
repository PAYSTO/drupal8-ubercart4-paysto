UC Paysto и Drupal 8 Ubercart 4

Модуль предназначен для интеграции магазина на Drupal 8 и Ubercart 4 с системой оплаты Paysto (сайты www.paysto.ru и www.paysto.com)
1)Для использования модуля на вашем сайте его необходимо вам установить. Можно установить через меню установки модулей Drupal, а можно путем записи каталога uc_paysto в директорию предназначенную для ваших сайтов "sites/all/modules" или "sites/default/modules"
2) После этого вы можете переходить в меню настройки методов оплаты "/admin/store/config/payment". 
3) Создаете новый способ оплаты в качестве типа выбираете "paysto". 
4) Переходите к настройке созданного метода оплаты, выставляете: 
- Метка - это то, что будет видеть пользователь в момент оформления заказа и совершения оплаты. 
- Your Merchant ID - номер магазина в системе Paysto
- Secret word for order verification - секретная фраза - также получается из интерфейса продавца системы Paysto;
- Описание заказа - то что будет видеть пользователь в момент совершения оплаты;
- НДС для продуктов (если у вас несколько типов товаров, то для каждого типа может быть свой НДС);
- НДС для доставки;
- Use server IP - использовать для обратных вызовов сервера из списка;
- Acceptable server list - список серверов;
- Order status after successfull payment - статус заказа после его выполнения



UC Paysto and Drupal 8 Ubercart 4

The module is designed to integrate a store on Drupal 8 and Ubercart 4 with the Paysto payment system (www.paysto.ru and www.paysto.com)
1) To use the module on your site you need to install it. You can install it through the installation menu of Drupal modules, or you can by writing the uc_paysto directory to a directory intended for your sites: "sites/all/modules" or "sites/default/modules"
2) After that, you can go to the configuration menu of payment methods "/admin/ store/config/payment".
3) Create a new payment method as the type choose "paysto".
4) Go to setting up the created payment method, expose:
- The label is what the user will see at the time of placing the order and making the payment.
- Your Merchant ID - the store number in the Paysto system
- Secret word for order verification - the secret phrase is also obtained from the interface of the seller of the Paysto system;
- Order description - what the user will see at the time of payment;
- VAT for products (if you have several types of goods, then each type may have its own VAT);
- VAT for delivery;
- Use server IP - use for server callbacks from the list;
- Acceptable server list - list of servers;
- Order status after successfull payment - order status after its completion