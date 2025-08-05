<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use Razorpay\Api\Api;
use Exception;


class RazorPay extends Controller
{

    public static function CreateOrderId($request)
    {

        $api = new Api($request['Razorpay_key_id'], $request['Razorpay_secret_id']);

        $orderRequest = [
            'amount' => $request['final_payable_amount'] * 100, // multiply by hundred because Razorpay takes amount in indian paisa
            'payment_capture' => 1,
            'currency' => 'INR',
            "receipt" => $request['enquiryId'],
        ];

        try {

            $order = $api->order->create($orderRequest)->toArray();

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'msg' => "An issue occured while initializing the RazorPay Payment Gateway : " . $e->getMessage(),
                'dev' => __CLASS__ . ' - ' . __LINE__
            ]);
        }
        if (!empty($order) && $order['id'] != null) {

            return response()->json([
                'status' => true,
                'msg' => "Order Id Created Successfully",
                'order_id' => $order['id'],
                'data' => $order
            ]);
        }
        return response()->json([
            'status' => false,
            'msg' => "Failed to create Order Id",
            'data' => null
        ]);
    }

    public static function checkPaymentStaus($request)
    {
        $api = new Api($request['Razorpay_key_id'], $request['Razorpay_secret_id']);

        try {

            $attributes  = [
                'razorpay_signature'  => $request['razorpay_signature'],
                'razorpay_payment_id'  => $request['razorpay_payment_id'],
                'razorpay_order_id' => $request['order_id']
            ];

            $api->utility->verifyPaymentSignature($attributes);

            $transaction_info = $api->payment->fetch($request['razorpay_payment_id'])->toArray();

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,
                'redirectUrl' => paymentSuccessFailureCallbackUrl($request['enquiry_id'], 'CAR', 'FAILURE'),
                'message' => $e->getMessage(),
                'line_no' => $e->getLine(),
                'file' => pathinfo($e->getFile())['basename']
            ]);
        }


        if (!empty($transaction_info)) {

            return response()->json([
                'status' => true,
                'data' => $transaction_info,
            ]);
        }

        return response()->json([
            'status' => false,
            'data' => null
        ]);
    }

    public static function confirmPaymentStaus($request)
    {
        try {

            $api = new Api($request['Razorpay_key_id'], $request['Razorpay_secret_id']);

            $params = [
                'amount' => $request['amount'],
                'currency' => 'INR'
            ];

            $api->payment->fetch($request['razorpay_payment_id'])->capture($params);

            $transaction_info = $api->payment->fetch($request['razorpay_payment_id'])->toArray();

        } catch (Exception $e) {

            if ($e->getMessage() != 'This payment has already been captured') {

                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($transaction_info)) {

            return response()->json([
                'status' => true,
                'data' => $transaction_info
            ]);
        }

        return response()->json([
            'status' => false,
            'data' => null
        ]);
    }

    #fetch
    public static function fetchPaymentStaus($request)
    {

        $api = new Api($request['Razorpay_key_id'], $request['Razorpay_secret_id']);
        $fetch_data = $api->order->fetch($request['order_id'])->payments()->toArray();

        try {

            $fetch_data = $api->order->fetch($request['order_id'])->payments()->toArray();

        } catch (\Exception $e) {

            return response()->json([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Fetch Service Issue' . $e->getMessage(),

            ]);
        }


        if (!empty($fetch_data)) {

            return response()->json([
                'status' => true,
                'data' => $fetch_data
            ]);
        }

        return response()->json([
            'status' => false,
            'data' => null
        ]);
    }
}