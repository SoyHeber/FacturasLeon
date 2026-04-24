<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Categoría</title>
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
        .row {
            margin-bottom: 15px;
        }
        .label {
            font-weight: bold;
            color: #374151;
        }
        .value {
            margin-top: 5px;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        a, button {
            background: #111827;
            color: white;
            padding: 10px 14px;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-edit {
            background: #2563eb;
        }
        .btn-delete {
            background: #dc2626;
        }
        form {
            display: inline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Detalle de Categoría</h1>

        <div class="row">
            <div class="label">ID</div>
            <div class="value">{{ $categoria->id }}</div>
        </div>

        <div class="row">
            <div class="label">Nombre</div>
            <div class="value">{{ $categoria->nombre }}</div>
        </div>

        <div class="row">
            <div class="label">Descripción</div>
            <div class="value">{{ $categoria->descripcion ?? 'Sin descripción' }}</div>
        </div>

        <div class="row">
            <div class="label">Estado</div>
            <div class="value">{{ $categoria->estado ? 'Activa' : 'Inactiva' }}</div>
        </div>

        <div class="row">
            <div class="label">Fecha de creación</div>
            <div class="value">{{ $categoria->created_at ? $categoria->created_at->format('d/m/Y H:i') : 'N/A' }}</div>
        </div>

        <div class="row">
            <div class="label">Última actualización</div>
            <div class="value">{{ $categoria->updated_at ? $categoria->updated_at->format('d/m/Y H:i') : 'N/A' }}</div>
        </div>

        <div class="actions">
            <a href="{{ route('categorias.index') }}">Volver</a>
            <a href="{{ route('categorias.edit', $categoria->id) }}" class="btn-edit">Editar</a>

            <form action="{{ route('categorias.destroy', $categoria->id) }}" method="POST" onsubmit="return confirm('¿Deseas eliminar esta categoría?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-delete">Eliminar</button>
            </form>
        </div>
    </div>
</body>
</html>