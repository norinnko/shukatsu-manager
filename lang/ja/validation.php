<?php

/*
| Japanese validation messages for the rules this app uses. Anything not
| listed falls back to the framework's English messages.
*/

return [
    'accepted' => ':attributeを承認してください。',
    'after_or_equal' => ':attributeは:date以降の日付にしてください。',
    'array' => ':attributeの形式が正しくありません。',
    'boolean' => ':attributeの値が正しくありません。',
    'date' => ':attributeは正しい日付ではありません。',
    'date_format' => ':attributeの形式が正しくありません。',
    'email' => ':attributeは正しいメールアドレスを入力してください。',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは存在しません。',
    'in' => '選択された:attributeは正しくありません。',
    'integer' => ':attributeは整数で入力してください。',
    'max' => [
        'array' => ':attributeは:max個以下にしてください。',
        'numeric' => ':attributeは:max以下にしてください。',
        'string' => ':attributeは:max文字以内で入力してください。',
    ],
    'min' => [
        'array' => ':attributeは:min個以上にしてください。',
        'numeric' => ':attributeは:min以上にしてください。',
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'required' => ':attributeを入力してください。',
    'string' => ':attributeは文字列で入力してください。',
    'unique' => 'その:attributeはすでに登録されています。',
    'url' => ':attributeは http:// または https:// から始まるURLを入力してください。',

    'attributes' => [
        'name' => '名前',
        'phase' => '区分',
        'stage' => '選考段階',
        'priority' => '志望度',
        'type' => '種類',
        'category' => 'カテゴリ',
        'company_id' => '企業',
        'memo' => 'メモ',
        'location' => '場所',
        'status' => '状態',
    ],
];
