<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoría</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f7f7f7;
        }
        .container {
            max-width: 700px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,.08);
        }
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        button, a {
            background: #111827;
            color: white;
            padding: 10px 14px;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-secondary {
            background: #6b7280;
        }
        .error-list {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .checkbox {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Editar Categoría</h1>

        @if ($errors->any())
            <div class="error-list">
                <strong>Corrige los siguientes errores:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('categorias.update', $categoria->id) }}" method="POST">
            @csrf
            @method('PUT')

            <label for="nombre">Nombre</label>
            <input
                type="text"
                name="nombre"
                id="nombre"
                value="{{ old('nombre', $categoria->nombre) }}"
                maxlength="100"
                required
            >

            <label for="descripcion">Descripción</label>
            <textarea
                name="descripcion"
                id="descripcion"
            >{{ old('descripcion', $categoria->descripcion) }}</textarea>

            <div class="checkbox">
                <label>
                    <input
                        type="checkbox"
                        name="estado"
                        value="1"
                        {{ old('estado', $categoria->estado) ? 'checked' : '' }}
                    >
                    Activa
                </label>
            </div>

            <div class="actions">
                <button type="submit">Actualizar</button>
                <a href="{{ route('categorias.index') }}" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>