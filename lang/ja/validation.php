<?php

return [
    'accepted' => ':attributeを承認してください。',
    'active_url' => ':attributeは正しいURLではありません。',
    'after' => ':attributeは:date以降の時刻を指定してください。',
    'alpha' => ':attributeはアルファベットのみにしてください。',
    'boolean' => ':attributeは真偽値（true/false）にしてください。',
    'confirmed' => ':attributeが確認用と一致しません。',
    'date' => ':attributeは正しい日付にしてください。',
    'date_format' => ':attributeは:format形式で指定してください。',
    'different' => ':attributeと:otherは異なる必要があります。',
    'digits' => ':attributeは:digits桁にしてください。',
    'email' => ':attributeは正しいメールアドレスの形式にしてください。',
    'exists' => '選択された:attributeは正しくありません。',
    'file' => ':attributeはファイルにしてください。',
    'filled' => ':attributeに入力してください。',
    'image' => ':attributeは画像にしてください。',
    'in' => '選択された:attributeは正しくありません。',
    'integer' => ':attributeは整数にしてください。',
    'ip' => ':attributeは正しいIPアドレスにしてください。',
    'max' => [
        'numeric' => ':attributeは:max以下にしてください。',
        'file' => ':attributeは:max KB以下にしてください。',
        'string' => ':attributeは:max文字以下にしてください。',
        'array' => ':attributeは:max個以下にしてください。',
    ],
    'min' => [
        'numeric' => ':attributeは:min以上にしてください。',
        'file' => ':attributeは:min KB以上にしてください。',
        'string' => ':attributeは:min文字以上にしてください。',
        'array' => ':attributeは:min個以上にしてください。',
    ],
    'numeric' => ':attributeは数字にしてください。',
    'required' => ':attributeは必須です。',
    'string' => ':attributeは文字列にしてください。',
    'unique' => 'この:attributeは既に登録されています。',

    'custom' => [
        'name' => [
            'required' => 'お名前を入力してください',
        ],

        'email' => [
            'required' => 'メールアドレスを入力してください',
            'email' => 'メールアドレスはメール形式で入力してください',
        ],

        'password' => [
            'required' => 'パスワードを入力してください',
            'min' => 'パスワードは8文字以上で入力してください',
            'confirmed' => 'パスワードと一致しません',
        ],
    ],

    'attributes' => [
        'user_id' => 'ユーザーID',
        'date' => '日付',
        'month' => '年月',
        'page' => 'ページ番号',
        'per_page' => '1ページあたりの件数',
        'clock_in' => '出勤時刻',
        'clock_out' => '退勤時刻',
        'comment' => '備考',
    ],
];
