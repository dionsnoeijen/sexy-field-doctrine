<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * @coversDefaultClass Tardigrades\SectionField\QueryComponents\TransformResults
 * @covers ::<private>
 */
final class TransformResultsTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var TransformResults */
    private $transformResults;

    public function setUp(): void
    {
        $this->transformResults = new TransformResults();
    }

    /**
     * @test
     */
    public function it_should_transform_a_flat_result_into_a_hierarchical_result(): void
    {
        $expected = json_decode('[{"participantSession":{"participantSessionAppointmentDate":"","uuid":"d0ce5e56-e9da-4e72-ae2b-2a59c83dfe7b","participantSessionSlug":"d0ce5e56-e9da-4e72-ae2b-2a59c83dfe7b"},"consultant":{"uuid":"","account":{"firstName":"","infix":"","lastName":"","uuid":"","accountSlug":""}},"accountHasRole":{"uuid":"76f1adae-772e-481a-9743-303feee099f4","account":{"firstName":"Gwen","infix":"","lastName":"Spencer","uuid":"492878a3-354f-419d-86d2-f78e212b81c7","accountSlug":"492878a3-354f-419d-86d2-f78e212b81c7"},"accountHasRoleSlug":"76f1adae-772e-481a-9743-303feee099f4"},"project":{"uuid":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df","projectSlug":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df"},"organisation":{"organisationName":"LTP","organisationType":"organisation","uuid":"0e230fcd-781f-46aa-8c19-188da3a0c462","organisationSlug":"0e230fcd-781f-46aa-8c19-188da3a0c462"},"product":{"uuid":"bab11893-045b-4c26-9cc5-586d879ae4ac","productSlug":"bab11893-045b-4c26-9cc5-586d879ae4ac"}},{"participantSession":{"participantSessionAppointmentDate":"","uuid":"80cefb12-12d7-4028-9a83-5ee573798cc9","participantSessionSlug":"80cefb12-12d7-4028-9a83-5ee573798cc9"},"consultant":{"uuid":"","account":{"firstName":"","infix":"","lastName":"","uuid":"","accountSlug":""}},"accountHasRole":{"uuid":"00465387-1490-4d10-b552-9ea182171249","account":{"firstName":"Melisa","infix":"de","lastName":"Bergnaum","uuid":"3842835c-af0f-49c9-af06-c19d3f3f8c8c","accountSlug":"3842835c-af0f-49c9-af06-c19d3f3f8c8c"},"accountHasRoleSlug":"00465387-1490-4d10-b552-9ea182171249"},"project":{"uuid":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df","projectSlug":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df"},"organisation":{"organisationName":"LTP","organisationType":"organisation","uuid":"0e230fcd-781f-46aa-8c19-188da3a0c462","organisationSlug":"0e230fcd-781f-46aa-8c19-188da3a0c462"},"product":{"uuid":"bab11893-045b-4c26-9cc5-586d879ae4ac","productSlug":"bab11893-045b-4c26-9cc5-586d879ae4ac"}},{"participantSession":{"participantSessionAppointmentDate":"","uuid":"75ea17d1-845b-414d-8f49-5379c0eac3c6","participantSessionSlug":"75ea17d1-845b-414d-8f49-5379c0eac3c6"},"consultant":{"uuid":"","account":{"firstName":"","infix":"","lastName":"","uuid":"","accountSlug":""}},"accountHasRole":{"uuid":"85a52156-934d-40bc-96b4-be29ad26e4ac","account":{"firstName":"Hershel","infix":"","lastName":"Howe","uuid":"b6d8eab3-2d1a-4d6a-851e-131aa650047b","accountSlug":"b6d8eab3-2d1a-4d6a-851e-131aa650047b"},"accountHasRoleSlug":"85a52156-934d-40bc-96b4-be29ad26e4ac"},"project":{"uuid":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df","projectSlug":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df"},"organisation":{"organisationName":"LTP","organisationType":"organisation","uuid":"0e230fcd-781f-46aa-8c19-188da3a0c462","organisationSlug":"0e230fcd-781f-46aa-8c19-188da3a0c462"},"product":{"uuid":"bab11893-045b-4c26-9cc5-586d879ae4ac","productSlug":"bab11893-045b-4c26-9cc5-586d879ae4ac"}},{"participantSession":{"participantSessionAppointmentDate":"","uuid":"a97e783f-f182-410e-bb22-d5cceffd6fd3","participantSessionSlug":"a97e783f-f182-410e-bb22-d5cceffd6fd3"},"consultant":{"uuid":"","account":{"firstName":"Sjaak","infix":"","lastName":"Afhaak","uuid":"","accountSlug":""}},"accountHasRole":{"uuid":"6d6e7bbb-47fd-4e7e-baaf-c77515dadaaa","account":{"firstName":"Annabel","infix":"","lastName":"Kovacek","uuid":"f05f47f5-a557-4bcc-966a-8619d2d468ca","accountSlug":"f05f47f5-a557-4bcc-966a-8619d2d468ca"},"accountHasRoleSlug":"6d6e7bbb-47fd-4e7e-baaf-c77515dadaaa"},"project":{"uuid":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df","projectSlug":"cf25ff3e-bfcf-443f-8581-e1c467f4e9df"},"organisation":{"organisationName":"LTP","organisationType":"organisation","uuid":"0e230fcd-781f-46aa-8c19-188da3a0c462","organisationSlug":"0e230fcd-781f-46aa-8c19-188da3a0c462"},"product":{"uuid":"bab11893-045b-4c26-9cc5-586d879ae4ac","productSlug":"bab11893-045b-4c26-9cc5-586d879ae4ac"}}]', true);

        $this->assertEquals(
            $expected,
            $this->transformResults->intoHierarchy($this->givenASetOfSessionResults())
        );
    }

    private function givenASetOfSessionResults(): array
    {
        return [
            [
                'participantSession_participantSessionAppointmentDate' => null,
                'participantSession_uuid' => 'd0ce5e56-e9da-4e72-ae2b-2a59c83dfe7b',
                'consultant_uuid' => null,
                'accountHasRole_uuid' => '76f1adae-772e-481a-9743-303feee099f4',
                'project_uuid' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationName' => 'LTP',
                'organisation_organisationType' => 'organisation',
                'organisation_uuid' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_uuid' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_firstName' => null,
                'consultant_account_infix' => null,
                'consultant_account_lastName' => null,
                'consultant_account_uuid' => null,
                'accountHasRole_account_firstName' => 'Gwen',
                'accountHasRole_account_infix' => null,
                'accountHasRole_account_lastName' => 'Spencer',
                'accountHasRole_account_uuid' => '492878a3-354f-419d-86d2-f78e212b81c7',
                'participantSession_participantSessionSlug' => 'd0ce5e56-e9da-4e72-ae2b-2a59c83dfe7b',
                'accountHasRole_accountHasRoleSlug' => '76f1adae-772e-481a-9743-303feee099f4',
                'project_projectSlug' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationSlug' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_productSlug' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_accountSlug' => null,
                'accountHasRole_account_accountSlug' => '492878a3-354f-419d-86d2-f78e212b81c7',
            ], [
                'participantSession_participantSessionAppointmentDate' => null,
                'participantSession_uuid' => '80cefb12-12d7-4028-9a83-5ee573798cc9',
                'consultant_uuid' => null,
                'accountHasRole_uuid' => '00465387-1490-4d10-b552-9ea182171249',
                'project_uuid' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationName' => 'LTP',
                'organisation_organisationType' => 'organisation',
                'organisation_uuid' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_uuid' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_firstName' => null,
                'consultant_account_infix' => null,
                'consultant_account_lastName' => null,
                'consultant_account_uuid' => null,
                'accountHasRole_account_firstName' => 'Melisa',
                'accountHasRole_account_infix' => 'de',
                'accountHasRole_account_lastName' => 'Bergnaum',
                'accountHasRole_account_uuid' => '3842835c-af0f-49c9-af06-c19d3f3f8c8c',
                'participantSession_participantSessionSlug' => '80cefb12-12d7-4028-9a83-5ee573798cc9',
                'accountHasRole_accountHasRoleSlug' => '00465387-1490-4d10-b552-9ea182171249',
                'project_projectSlug' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationSlug' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_productSlug' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_accountSlug' => null,
                'accountHasRole_account_accountSlug' => '3842835c-af0f-49c9-af06-c19d3f3f8c8c',
            ], [
                'participantSession_participantSessionAppointmentDate' => null,
                'participantSession_uuid' => '75ea17d1-845b-414d-8f49-5379c0eac3c6',
                'consultant_uuid' => null,
                'accountHasRole_uuid' => '85a52156-934d-40bc-96b4-be29ad26e4ac',
                'project_uuid' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationName' => 'LTP',
                'organisation_organisationType' => 'organisation',
                'organisation_uuid' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_uuid' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_firstName' => null,
                'consultant_account_infix' => null,
                'consultant_account_lastName' => null,
                'consultant_account_uuid' => null,
                'accountHasRole_account_firstName' => 'Hershel',
                'accountHasRole_account_infix' => null,
                'accountHasRole_account_lastName' => 'Howe',
                'accountHasRole_account_uuid' => 'b6d8eab3-2d1a-4d6a-851e-131aa650047b',
                'participantSession_participantSessionSlug' => '75ea17d1-845b-414d-8f49-5379c0eac3c6',
                'accountHasRole_accountHasRoleSlug' => '85a52156-934d-40bc-96b4-be29ad26e4ac',
                'project_projectSlug' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationSlug' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_productSlug' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_accountSlug' => null,
                'accountHasRole_account_accountSlug' => 'b6d8eab3-2d1a-4d6a-851e-131aa650047b',
            ], [
                'participantSession_participantSessionAppointmentDate' => null,
                'participantSession_uuid' => 'a97e783f-f182-410e-bb22-d5cceffd6fd3',
                'consultant_uuid' => null,
                'accountHasRole_uuid' => '6d6e7bbb-47fd-4e7e-baaf-c77515dadaaa',
                'project_uuid' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationName' => 'LTP',
                'organisation_organisationType' => 'organisation',
                'organisation_uuid' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_uuid' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_firstName' => 'Sjaak',
                'consultant_account_infix' => null,
                'consultant_account_lastName' => 'Afhaak',
                'consultant_account_uuid' => null,
                'accountHasRole_account_firstName' => 'Annabel',
                'accountHasRole_account_infix' => null,
                'accountHasRole_account_lastName' => 'Kovacek',
                'accountHasRole_account_uuid' => 'f05f47f5-a557-4bcc-966a-8619d2d468ca',
                'participantSession_participantSessionSlug' => 'a97e783f-f182-410e-bb22-d5cceffd6fd3',
                'accountHasRole_accountHasRoleSlug' => '6d6e7bbb-47fd-4e7e-baaf-c77515dadaaa',
                'project_projectSlug' => 'cf25ff3e-bfcf-443f-8581-e1c467f4e9df',
                'organisation_organisationSlug' => '0e230fcd-781f-46aa-8c19-188da3a0c462',
                'product_productSlug' => 'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'consultant_account_accountSlug' => null,
                'accountHasRole_account_accountSlug' => 'f05f47f5-a557-4bcc-966a-8619d2d468ca',
            ]
        ];
    }
}
