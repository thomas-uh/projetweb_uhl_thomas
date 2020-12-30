<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Doctrine\ORM\EntityManager;

use App\Controllers\ProductHelper;
use Purchase;

class OrderController
{
    private EntityManager $em;
    private ProductHelper $productHelper;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->productHelper = new ProductHelper($em);
    }

    public function order(Request $request, Response $response, array $args): Response {
        $authHeader = $request->getHeaderLine('authorization');
        $login = JWTTokenHelper::decodeJWTToken($authHeader)['user_login'] ?? '';

        $clientRepo = $this->em->getRepository('Client');
        $client = $clientRepo->findOneBy([
            'login' => $login,
        ]);

        if ($client == null) {
            $response->getBody()->write(json_encode(['success' => false]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        $body = $request->getParsedBody();
        $json = $body['products'] ?? "";
        $data = json_decode($json, true);

        $productPurchases = $this->productHelper->parseProductPurchases($data);

        if (count($productPurchases) <= 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        }

        $order = new Purchase();
        $order->setDate(date_create());
        $order->setBuyer($client);
        $client->addOrder($order);
        
        foreach($productPurchases as $productPurchase) {
            $productPurchase->setOrder($order);
            $this->em->persist($productPurchase);
        }
        
        $this->em->persist($order);
        $this->em->persist($client);
        $this->em->flush();

        $response->getBody()->write(json_encode([
            'success' => true,
            ]));

        $token_jwt = JWTTokenHelper::generateJWTToken($login);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $token_jwt)
            ->withStatus(200);
    }   

}