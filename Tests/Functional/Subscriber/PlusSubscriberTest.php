<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagPaymentPayPalUnified\Tests\Functional\Subscriber;

use Enlight_Template_Manager;
use SwagPaymentPayPalUnified\Components\PaymentMethodProvider;
use SwagPaymentPayPalUnified\Subscriber\Plus;
use SwagPaymentPayPalUnified\Tests\Functional\DatabaseTestCaseTrait;
use SwagPaymentPayPalUnified\Tests\Functional\SettingsHelperTrait;
use SwagPaymentPayPalUnified\Tests\Mocks\DummyController;
use SwagPaymentPayPalUnified\Tests\Mocks\OrderDataServiceMock;
use SwagPaymentPayPalUnified\Tests\Mocks\PaymentInstructionServiceMock;
use SwagPaymentPayPalUnified\Tests\Mocks\PaymentResourceMock;
use SwagPaymentPayPalUnified\Tests\Mocks\ViewMock;

class PlusSubscriberTest extends \PHPUnit_Framework_TestCase
{
    use DatabaseTestCaseTrait;
    use SettingsHelperTrait;

    public function test_can_be_created()
    {
        $subscriber = $this->getSubscriber();
        $this->assertNotNull($subscriber);
    }

    public function test_getSubscribedEvents_has_correct_events()
    {
        $events = Plus::getSubscribedEvents();
        $this->assertEquals('onPostDispatchCheckout', $events['Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout']);
    }

    public function test_onPostDispatchCheckout_should_return_payment_method_inactive()
    {
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $paymentMethodProvider->setPaymentMethodActiveFlag(false);

        $subscriber = $this->getSubscriber();

        $view = new ViewMock(new Enlight_Template_Manager());
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setActionName('finish');

        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertNull($view->getAssign('paypalUnifiedUsePlus'));

        $paymentMethodProvider->setPaymentMethodActiveFlag(true);
    }

    public function test_onPostDispatchCheckout_should_return_because_no_settings_exists()
    {
        $subscriber = $this->getSubscriber();

        $view = new ViewMock(new Enlight_Template_Manager());
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setActionName('finish');

        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertNull($view->getAssign('paypalUnifiedUsePlus'));
    }

    public function test_onPostDispatchCheckout_should_return_because_is_express_checkout()
    {
        $subscriber = $this->getSubscriber();

        $view = new ViewMock(new Enlight_Template_Manager());
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setActionName('finish');
        $request->setParam('expressCheckout', true);

        $this->createTestSettings();

        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertNull($view->getAssign('paypalUnifiedUsePlus'));
    }

    public function test_onPostDispatchCheckout_should_return_because_the_action_is_invalid()
    {
        $subscriber = $this->getSubscriber();

        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setActionName('invalidSuperAction');
        $view = new ViewMock(new Enlight_Template_Manager());
        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $this->createTestSettings();

        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertNull($view->getAssign('paypalUnifiedUsePlus'));
    }

    public function test_onPostDispatchCheckout_should_return_because_plus_is_inactive()
    {
        $subscriber = $this->getSubscriber();

        $view = new ViewMock(new Enlight_Template_Manager());
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setActionName('finish');
        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $this->createTestSettings(true, false);

        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertNull($view->getAssign('paypalUnifiedUsePlus'));
    }

    public function test_onPostDispatchCheckout_should_assign_value_usePayPalPlus()
    {
        $subscriber = $this->getSubscriber();

        $view = new ViewMock(new Enlight_Template_Manager());
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setActionName('finish');
        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $this->createTestSettings();

        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertTrue((bool) $view->getAssign('paypalUnifiedUsePlus'));
    }

    public function test_onPostDispatchCheckout_should_assign_error_code()
    {
        $subscriber = $this->getSubscriber();

        $view = new ViewMock(new Enlight_Template_Manager());
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setActionName('finish');
        $request->setParam('paypal_unified_error_code', 5);

        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $this->createTestSettings();

        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertTrue((bool) $view->getAssign('paypalUnifiedUsePlus'));
        $this->assertEquals('5', $view->getAssign('paypalUnifiedErrorCode'));
    }

    public function test_onPostDispatchCheckout_overwritePaymentName()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings(true, true, false, false, true);

        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', ['additional' => ['payment' => ['id' => $unifiedPaymentId]]]);
        $view->assign('sPayments', [['id' => $unifiedPaymentId]]);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('shippingPayment');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $viewAssignments = $view->getAssign();

        $this->assertEquals('Test Plus Name', $viewAssignments['sPayment']['description']);
        $this->assertEquals('<br>Test Plus Description', $viewAssignments['sPayment']['additionaldescription']);

        $this->assertEquals('Test Plus Name', $viewAssignments['sUserData']['additional']['payment']['description']);
        $this->assertEquals('<br>Test Plus Description', $viewAssignments['sUserData']['additional']['payment']['additionaldescription']);

        $this->assertEquals('Test Plus Name', $viewAssignments['sPayments'][0]['description']);
        $this->assertEquals('<br>Test Plus Description', $viewAssignments['sPayments'][0]['additionaldescription']);
    }

    public function test_onPostDispatchSecure_handleShippingPaymentDispatch_could_not_create_payment_struct()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings(true, true, true);
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', ['sCurrencyName' => 'throwException']);
        $view->assign('sUserData', []);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('shippingPayment');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertNull($view->getAssign('paypalUnifiedRestylePaymentSelection'));
    }

    public function test_onPostDispatchSecure_sets_restyle_correctly_if_setting_is_on()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings(true, true, true);
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', []);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('shippingPayment');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertTrue((bool) $view->getAssign('paypalUnifiedRestylePaymentSelection'));
    }

    public function test_onPostDispatchSecure_sets_restyle_correctly_if_setting_is_off()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings();
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', []);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('shippingPayment');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertFalse((bool) $view->getAssign('paypalUnifiedRestylePaymentSelection'));
    }

    public function test_onPostDispatchSecure_handleShippingPaymentDispatch_handleIntegratingThirdPartyMethods()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings(true, true, false, true);
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', []);

        $payments = require __DIR__ . '/_fixtures/sPayments.php';
        $payments[] = [
            'id' => $unifiedPaymentId,
            'name' => 'SwagPaymentPayPalUnified',
            'description' => 'PayPal, Lastschrift oder Kreditkarte',
            'additionaldescription' => 'Bezahlung per PayPal - einfach, schnell und sicher. Zahlung per Lastschrift oder Kreditkarte ist auch ohne PayPal Konto möglich',
        ];
        $view->assign('sPayments', $payments);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('shippingPayment');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);
        $paymentsForPaymentWall = json_decode($view->getAssign('paypalUnifiedPlusPaymentMethodsPaymentWall'), true)[0];

        $this->assertEquals('http://4', $paymentsForPaymentWall['redirectUrl']);
        $this->assertEquals('Rechnung', $paymentsForPaymentWall['methodName']);
        $this->assertEquals('Sie zahlen einfach und bequem auf Rechnung.', $paymentsForPaymentWall['description']);
    }

    public function test_onPostDispatchSecure_handleFinishDispatch()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings();
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', []);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('finish');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertEquals('testTransactionId', $view->getAssign('sTransactionumber'));
    }

    public function test_onPostDispatchSecure_handleFinishDispatch_add_paymentInstructions()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings();
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', []);
        $view->assign('sOrderNumber', 'getPaymentInstructions');
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('finish');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $this->assertEquals('testReference', $view->getAssign('sTransactionumber'));
        $this->assertEquals('testAccountHolder', $view->getAssign('paypalUnifiedPaymentInstructions')['accountHolder']);
    }

    public function test_onPostDispatchSecure_handleConfirmDispatch()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings();
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', []);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('confirm');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);
        $viewAssignments = $view->getAssign();

        $this->assertEquals('PAY-9HW62735H82101921LLK3D4I', $viewAssignments['paypalUnifiedRemotePaymentId']);
        $this->assertEquals('https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-49W9096312907153R', $viewAssignments['paypalUnifiedApprovalUrl']);
        $this->assertEquals('de_DE', $viewAssignments['paypalUnifiedLanguageIso']);
    }

    public function test_onPostDispatchSecure_handleConfirmDispatch_should_return_because_of_no_paymentStruct()
    {
        $subscriber = $this->getSubscriber();
        $this->createTestSettings();
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', ['sCurrencyName' => 'throwException']);
        $view->assign('sUserData', []);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('confirm');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);
        $viewAssignments = $view->getAssign();

        $this->assertNull($viewAssignments['paypalUnifiedRemotePaymentId']);
        $this->assertNull($viewAssignments['paypalUnifiedApprovalUrl']);
        $this->assertNull($viewAssignments['paypalUnifiedLanguageIso']);
    }

    public function test_onPostDispatchSecure_handleConfirmDispatch_return_came_from_step_two()
    {
        $session = Shopware()->Session();
        $subscriber = $this->getSubscriber();
        $this->createTestSettings();
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $unifiedPaymentId = $paymentMethodProvider->getPaymentId(Shopware()->Container()->get('dbal_connection'));
        $session->offsetSet('paypalUnifiedCameFromPaymentSelection', true);
        $session->offsetSet('paypalUnifiedRemotePaymentId', 'PAY-TestRemotePaymentId');

        $view = new ViewMock(new Enlight_Template_Manager());
        $view->assign('sPayment', ['id' => $unifiedPaymentId]);
        $view->assign('sBasket', []);
        $view->assign('sUserData', []);
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request->setActionName('confirm');
        $enlightEventArgs = new \Enlight_Controller_ActionEventArgs([
            'subject' => new DummyController($request, $view, $response),
        ]);

        $subscriber->onPostDispatchCheckout($enlightEventArgs);

        $viewAssignments = $view->getAssign();

        $this->assertEquals('PAY-TestRemotePaymentId', $viewAssignments['paypalUnifiedRemotePaymentId']);

        $session->offsetUnset('paypalUnifiedCameFromPaymentSelection');
        $session->offsetUnset('paypalUnifiedRemotePaymentId');
    }

    public function test_addPaymentMethodsAttributes_payment_methods_inactive()
    {
        $paymentMethodProvider = new PaymentMethodProvider(Shopware()->Container()->get('models'));
        $paymentMethodProvider->setPaymentMethodActiveFlag(false);

        $eventArgs = new \Enlight_Event_EventArgs();
        $eventArgs->setReturn([
            'test' => 'foo',
        ]);

        $subscriber = $this->getSubscriber();

        $result = $subscriber->addPaymentMethodsAttributes($eventArgs);

        $this->assertArraySubset(['test' => 'foo'], $result);

        $paymentMethodProvider->setPaymentMethodActiveFlag(true);
    }

    public function test_addPaymentMethodsAttributes_unified_inactive()
    {
        $this->createTestSettings(false);
        $eventArgs = new \Enlight_Event_EventArgs();
        $eventArgs->setReturn([
            'test' => 'foo',
        ]);

        $subscriber = $this->getSubscriber();

        $result = $subscriber->addPaymentMethodsAttributes($eventArgs);

        $this->assertArraySubset(['test' => 'foo'], $result);
    }

    public function test_addPaymentMethodsAttributes_plus_inactive()
    {
        $this->createTestSettings(true, false);
        $eventArgs = new \Enlight_Event_EventArgs();
        $eventArgs->setReturn([
            'test' => 'foo',
        ]);

        $subscriber = $this->getSubscriber();

        $result = $subscriber->addPaymentMethodsAttributes($eventArgs);

        $this->assertArraySubset(['test' => 'foo'], $result);
    }

    public function test_addPaymentMethodsAttributes_do_not_integrate_third_party_methods()
    {
        $this->createTestSettings();
        $eventArgs = new \Enlight_Event_EventArgs();
        $eventArgs->setReturn([
            'test' => 'foo',
        ]);

        $subscriber = $this->getSubscriber();

        $result = $subscriber->addPaymentMethodsAttributes($eventArgs);

        $this->assertArraySubset(['test' => 'foo'], $result);
    }

    public function test_addPaymentMethodsAttributes_attribute_not_set()
    {
        $this->createTestSettings(true, true, false, true);
        $eventArgs = new \Enlight_Event_EventArgs();
        $eventArgs->setReturn([
            [
                'id' => 5,
            ],
            [
                'id' => 6,
            ],
        ]);

        $subscriber = $this->getSubscriber();

        $result = $subscriber->addPaymentMethodsAttributes($eventArgs);

        $this->assertArraySubset([['id' => 5], ['id' => 6]], $result);
    }

    public function test_addPaymentMethodsAttributes()
    {
        $this->createTestSettings(true, true, false, true);
        Shopware()->Container()->get('dbal_connection')->executeQuery(
            "INSERT INTO `s_core_paymentmeans_attributes` (`paymentmeanID`, `swag_paypal_unified_display_in_plus_iframe`) VALUES ('6', '1');"
        );
        $eventArgs = new \Enlight_Event_EventArgs();
        $eventArgs->setReturn([
            [
                'id' => 5,
            ],
            [
                'id' => 6,
            ],
        ]);

        $subscriber = $this->getSubscriber();

        $result = $subscriber->addPaymentMethodsAttributes($eventArgs);

        $this->assertArraySubset(
            [
                ['id' => 5],
                [
                    'id' => 6,
                    'swag_paypal_unified_display_in_plus_iframe' => 1,
                ],
            ],
            $result
        );
    }

    /**
     * @param bool $active
     * @param bool $plusActive
     * @param bool $restylePaymentSelection
     * @param bool $integrateThirdPartyMethods
     * @param bool $overwritePaymentName
     */
    private function createTestSettings(
        $active = true,
        $plusActive = true,
        $restylePaymentSelection = false,
        $integrateThirdPartyMethods = false,
        $overwritePaymentName = false
    ) {
        $this->insertGeneralSettingsFromArray([
            'shopId' => 1,
            'clientId' => 'test',
            'clientSecret' => 'test',
            'sandbox' => true,
            'showSidebarLogo' => true,
            'logoImage' => 'TEST',
            'active' => $active,
        ]);

        $plusSettings = [
            'shopId' => 1,
            'active' => $plusActive,
            'restyle' => $restylePaymentSelection,
            'integrateThirdPartyMethods' => $integrateThirdPartyMethods,
        ];

        if ($overwritePaymentName) {
            $plusSettings['paymentName'] = 'Test Plus Name';
            $plusSettings['paymentDescription'] = 'Test Plus Description';
        }

        $this->insertPlusSettingsFromArray($plusSettings);
    }

    /**
     * @return Plus
     */
    private function getSubscriber()
    {
        return new Plus(
            Shopware()->Container()->get('paypal_unified.settings_service'),
            Shopware()->Container()->get('paypal_unified.dependency_provider'),
            Shopware()->Container()->get('snippets'),
            Shopware()->Container()->get('dbal_connection'),
            new PaymentInstructionServiceMock(),
            new OrderDataServiceMock(),
            Shopware()->Container()->get('paypal_unified.plus.payment_builder_service'),
            Shopware()->Container()->get('paypal_unified.client_service'),
            new PaymentResourceMock(),
            Shopware()->Container()->get('paypal_unified.exception_handler_service')
        );
    }
}
