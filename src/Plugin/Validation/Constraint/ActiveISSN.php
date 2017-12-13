<?php

namespace Drupal\blender\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the ISSN value corresponds to an active journal
 *
 * @Constraint(
 *    id = "active_issn",
 *    label = @Translation("Active Journal ISSN", context = "Validation"),
 * )
 */
class ActiveISSN extends Constraint
{
  public $bad_issn = "'%value' not a valid ISSN. It should be of the form XXXX-XXXX.";

  public $bad_request = "There was a problem connecting to CrossRef. Try again later.";

  public $not_found = "The ISSN %value was not found on CrossRef.";

  public $not_active = "The journal with ISSN %value is not actively depositing articles.";

}


?>
