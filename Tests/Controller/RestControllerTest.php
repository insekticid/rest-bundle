<?php

namespace Lemon\RestBundle\Tests\Controller;

use Doctrine\ORM\AbstractQuery;
use Lemon\RestBundle\Tests\Fixtures\Car;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\Tools\SchemaTool;
use Lemon\RestBundle\Tests\Fixtures\Person;

class RestControllerTest extends WebTestCase
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\Client
     */
    protected $client;
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;
    /**
     * @var \Doctrine\Bundle\DoctrineBundle\Registry
     */
    protected $doctrine;
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;
    /**
     * @var \JMS\Serializer\Serializer
     */
    protected $serializer;

    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $this->doctrine = $this->container->get('doctrine');
        $this->em = $this->doctrine->getManager();
        $this->serializer = $this->container->get('jms_serializer');

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->doctrine->getConnection()->beginTransaction();

        $registry = $this->container->get('lemon.rest.object_registry');
        $registry->addClass('person', 'Lemon\RestBundle\Tests\Fixtures\Person');
    }

    public function tearDown()
    {
        $this->doctrine->getConnection()->rollback();
    }

    protected function makeRequest($method, $uri, $content = null)
    {
        $request = Request::create(
            $uri,
            $method,
            $parameters = array(),
            $cookies = array(),
            $files = array(),
            $server = array(
                'HTTP_ACCEPT' => 'application/json',
            ),
            $content
        );
        return $request;
    }

    public function testGetAction()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $person = new Person();
        $person->name = "Stan Lemon";

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest('GET', '/person/' . $person->id);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->getAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $this->assertEquals($person->id, $data->id);
        $this->assertEquals($person->name, $data->name);
    }

    public function testPostAction()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $request = $this->makeRequest(
            'POST',
            '/person',
            json_encode(array('name' => 'Stan Lemon'))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->postAction($request, 'person');

        $data = json_decode($response->getContent());

        $this->assertEquals($data->name, "Stan Lemon");

        $this->em->clear();

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals("Stan Lemon", $refresh->name);
    }

    public function testPutAction()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $person = new Person();
        $person->name = "Stan Lemon";

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array('id' => $person->id, 'name' => $person->name))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->putAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $this->assertEquals($person->id, $data->id);
        $this->assertEquals($person->name, $data->name);

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
    }

    public function testDeleteAction()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $person = new Person();
        $person->name = "Stan Lemon";

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest('DELETE', '/person/1');

        $person = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $person->id
        ));

        $this->assertNotNull($person);

        /** @var \Symfony\Component\HttpFoundation\Response() $response */
        $response = $controller->deleteAction($request, 'person', 1);

        $this->assertEquals("null", $response->getContent());
        $this->assertEquals(204, $response->getStatusCode());

        $person = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $person->id
        ));

        $this->assertNull($person);
    }

    public function testPutActionWithoutIdInPayload()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $person = new Person();
        $person->name = "Stan Lemon";

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array('name' => $person->name))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->putAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $this->assertEquals($person->id, $data->id);
        $this->assertEquals($person->name, $data->name);

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
    }

    public function testPostActionWithNestedCollection()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $request = $this->makeRequest(
            'POST',
            '/person',
            json_encode(array(
                'name' => 'Stan Lemon',
                'cars' => array(
                    array(
                        'name' => 'Honda',
                        'year' => 2006,
                    )
                )
            ))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->postAction($request, 'person');

        $this->em->clear();

        $data = json_decode($response->getContent());

        $this->assertEquals($data->name, "Stan Lemon");

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals("Stan Lemon", $refresh->name);
        $this->assertCount(1, $refresh->cars);
        $this->assertEquals("Honda", $refresh->cars[0]->name);
        $this->assertEquals(2006, $refresh->cars[0]->year);
    }

    public function testPostActionWithNestedEntity()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $request = $this->makeRequest(
            'POST',
            '/person',
            json_encode(array(
                'name' => 'Stan Lemon',
                'mother' => array(
                    'name' => 'Sharon Lemon'
                )
            ))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->postAction($request, 'person');

        $this->em->clear();

        $data = json_decode($response->getContent());

        $this->assertEquals($data->name, "Stan Lemon");

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals("Stan Lemon", $refresh->name);
        $this->assertNotNull($refresh->mother);
        $this->assertEquals("Sharon Lemon", $refresh->mother->name);
    }

    public function testPutActionWithNestedCollectionAndExistingItem()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $car = new Car();
        $car->name = 'Honda';
        $car->year = 2006;

        $person = new Person();
        $person->name = "Stan Lemon";
        $person->cars[] = $car;

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array(
                'name' => $person->name,
                'cars' => array(
                    array(
                        'id' => $car->id,
                        'name' => "Honda Odyssey",
                        'year' => 2006
                    )
                )
            ))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->putAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
        $this->assertCount(1, $refresh->cars);
        $this->assertEquals("Honda Odyssey", $refresh->cars[0]->name);
        $this->assertEquals(2006, $refresh->cars[0]->year);
    }

    public function testPutActionWithNestedCollectionAndExistingItemAndNewItem()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $car = new Car();
        $car->name = 'Honda';
        $car->year = 2006;

        $person = new Person();
        $person->name = "Stan Lemon";
        $person->cars[] = $car;

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array(
                'name' => $person->name,
                'cars' => array(
                    array(
                        'id' => $car->id,
                        'name' => "Honda Odyssey",
                        'year' => 2006,
                    ),
                    array(
                        'name' => "Mercedes Benz 300c",
                        'year' => 2013,
                    )
                )
            ))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->putAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
        $this->assertCount(2, $refresh->cars);
        $this->assertEquals("Honda Odyssey", $refresh->cars[0]->name);
        $this->assertEquals(2006, $refresh->cars[0]->year);
        $this->assertEquals("Mercedes Benz 300c", $refresh->cars[1]->name);
        $this->assertEquals(2013, $refresh->cars[1]->year);
    }

    public function testPutActionWithNestedCollectionAndRemoveExistingItem()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $car1 = new Car();
        $car1->name = 'Honda';
        $car1->year = 2006;

        $car2 = new Car();
        $car2->name = 'Mercedes Benz';
        $car2->year = 2013;

        $person = new Person();
        $person->name = "Stan Lemon";
        $person->cars[] = $car1;
        $person->cars[] = $car2;

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $person->id
        ));

        $this->assertCount(2, $refresh->cars);

        $this->em->clear($refresh);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array(
                'name' => $person->name,
                'cars' => array(
                    array(
                        'id' => $car1->id,
                        'name' => "Honda Odyssey",
                        'year' => 2006,
                    )
                )
            ))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->putAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
        $this->assertCount(1, $refresh->cars);
        $this->assertEquals("Honda Odyssey", $refresh->cars[0]->name);
        $this->assertEquals(2006, $refresh->cars[0]->year);
    }

    public function testPutActionWithNestedCollectionAndRemoveAllExistingItems()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $car1 = new Car();
        $car1->name = 'Honda';
        $car1->year = 2006;

        $car2 = new Car();
        $car2->name = 'Mercedes Benz';
        $car2->year = 2013;

        $person = new Person();
        $person->name = "Stan Lemon";
        $person->cars[] = $car1;
        $person->cars[] = $car2;

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $person->id
        ));

        $this->assertCount(2, $refresh->cars);

        $this->em->clear($refresh);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array(
                'name' => $person->name,
            ))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->putAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $data->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
        $this->assertCount(0, $refresh->cars);
    }

    public function testPutActionWithNestedEntity()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $mother = new Person();
        $mother->name = "Sharon Lemon";

        $person = new Person();
        $person->name = "Stan Lemon";
        $person->mother = $mother;

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array(
                'name' => $person->name,
                'mother' => array(
                    'id' => $mother->id,
                    'name' => $mother->name,
                )
            ))
        );

        $controller->putAction($request, 'person', 1);

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $person->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
        $this->assertNotNull($refresh->mother);
        $this->assertEquals($person->mother->id, $refresh->mother->id);
        $this->assertEquals($person->mother->name, $refresh->mother->name);
    }

    public function testPutActionWithNestedEntityRemoved()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $mother = new Person();
        $mother->name = "Sharon Lemon";

        $person = new Person();
        $person->name = "Stan Lemon";
        $person->mother = $mother;

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest(
            'PUT',
            '/person/' . $person->id,
            json_encode(array(
                'name' => $person->name,
            ))
        );

        $controller->putAction($request, 'person', 1);

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $person->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->id, $refresh->id);
        $this->assertEquals($person->name, $refresh->name);
        $this->assertNull($refresh->mother);

        // TODO: Determine if we should remove more than the association in this scenario
        /**
        $refreshMother = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $mother->id
        ));

        $this->assertNull($refreshMother);
        **/
    }

    public function testPostActionWithInvalidAttribute()
    {
        $query = $this->em->createQuery("SELECT COUNT(p.id) FROM Lemon\RestBundle\Tests\Fixtures\Person p");
        $total = $query->execute(array(), AbstractQuery::HYDRATE_SINGLE_SCALAR);

        $controller = $this->container->get('lemon.rest.controller');

        $request = $this->makeRequest(
            'POST',
            '/person',
            json_encode(array('name' => ''))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->postAction($request, 'person');

        $this->em->clear();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(
            $total,
            $this->em->createQuery("SELECT COUNT(p.id) FROM Lemon\RestBundle\Tests\Fixtures\Person p")
                ->execute(array(), AbstractQuery::HYDRATE_SINGLE_SCALAR)
        );
    }

    public function testPutActionWithInvalidAttribute()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $person = new Person();
        $person->name = "Stan Lemon";

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest(
            'POST',
            '/person/' . $person->id,
            json_encode(array('name' => ''))
        );

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->putAction($request, 'person', $person->id);

        $this->assertEquals(400, $response->getStatusCode());

        $refresh = $this->em->getRepository('Lemon\RestBundle\Tests\Fixtures\Person')->findOneBy(array(
            'id' => $person->id
        ));

        $this->assertNotNull($refresh);
        $this->assertEquals($person->name, $refresh->name);
    }

    public function testGetActionWithReadOnly()
    {
        $controller = $this->container->get('lemon.rest.controller');

        $person = new Person();
        $person->name = "Stan Lemon";
        $person->ssn = '123-45-678';

        $this->em->persist($person);
        $this->em->flush($person);
        $this->em->clear($person);

        $request = $this->makeRequest('GET', '/person/' . $person->id);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $controller->getAction($request, 'person', 1);

        $data = json_decode($response->getContent());

        $this->assertEquals($person->ssn, $data->ssn);
    }
}