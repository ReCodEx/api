<?php

namespace App\Model\View;

class TestView extends MetaView
{
    /**
     * @checked_param format:group group
     * @checked_param format:uuid user_id
     */
    public function endpoint($group, $user_id)
    {
        $params = $this->getTypedParams();
        $formattedGroup = $params["group"];
        var_dump($formattedGroup);

        // $a = new GroupFormat();
        // $a->validate();
    }
}
