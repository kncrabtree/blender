<?php

namespace Drupal\blender\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ActiveISSN constraint
 */
class ActiveISSNValidator extends ConstraintValidator
{

  /**
  * {@inheritdoc}
  */
  public function validate($items, Constraint $constraint) {

    foreach ($items as $item) {
      //Verify that ISSN matches XXXX-XXXX:
      $re = "/^\d{4}-\d{3}[\dxX]$/";
      if(!preg_match($re, $item->value)) {
        $this->context->addViolation($constraint->bad_issn, ['%value' => $item->value]);
      }
      else
      {
        //try to fetch from CrossRef
        $url = "https://api.crossref.org/journals/".$item->value;

        $client = \Drupal::httpClient();

        try {
          $response = $client->get($url, [
            'headers' => [
              "User-Agent" => "Crabtree Lab (http://crabtreelab.ucdavis.edu; mailto:kncrabtree@ucdavis.edu)"
            ],
            'http_errors' => false,
          ]);

          if($response->getStatusCode() >= 500)
          {
            $this->context->addViolation($constraint->bad_request, ['%value' => $item->value]);
          }
          elseif($response->getStatusCode() >= 400)
          {
            $this->context->addViolation($constraint->not_found, ['%value' => $item->value]);
          }
          else
          {
            $data = json_decode($response->getBody(),true);

            if($data['message']['flags']['deposits-articles'] == false)
            {
              $this->context->addViolation($constraint->not_active, ['%value' => $item->value]);
            }
          }

        }
        catch (RequestException $e) {
          $this->context->addViolation($constraint->bad_request, ['%value' => $item->value]);
        }

      }

    }

  }
}


?>
