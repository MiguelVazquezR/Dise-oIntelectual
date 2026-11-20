<?php

namespace App\Enums;

enum AwsFaceOperation: string
{
    case IndexFaces = 'index_faces';
    case SearchFacesByImage = 'search_faces_by_image';
    case DetectFaces = 'detect_faces';
    case DeleteFaces = 'delete_faces';

    public function label(): string
    {
        return match ($this) {
            self::IndexFaces => 'Registro de rostro',
            self::SearchFacesByImage => 'Búsqueda por imagen',
            self::DetectFaces => 'Detección de rostro',
            self::DeleteFaces => 'Baja de rostro',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
