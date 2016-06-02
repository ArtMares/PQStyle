<?php

/**
 * @author  ArtMares (Dmitriy Dergachev)
 * @date    13.04.2016
 * @update  02.06.2016
 * @version 0.3
 */

/**
 * Class Style
 * @date    13.04.2016
 * @version 0.1
 * Класс отвечающий за оформление приложения и его компонентов
 *
 * Основное назвачение класса загрузка и хранение оформлений для приложения и его компонентов
 * При инициализации класса передается обязательный аргумент, название дочернего каталога приложения.
 * В которм будут храниться темы оформления для приложения
 *
 * Дочерний каталог должен выглядеть следующим образом
 *
 * AppDir - Директория приложения
 * |---ChildDir - Дочерний каталог в котором расположены все темы приложения
 *     |---ThemeName1
 *     |   |---FileName1.qss
 *     |   |---FileName2.qss
 *     |
 *     |---ThemeName2
 *         |---FileName1.qss
 *         |---FileName2.qss
 *
 * Аддон сам получит все каталоги из дочерней директории приложения и вложенные файлы qss
 *
 * Внимание! Аддон пропустит все остальные файлы
 *
 * Для того чтобы задать тему оформления приложения необходимо вызвать метод setTheme('ThemeName')
 * Метод может быть вызван двумя способами:
 *  $style = new Style('Themes');
 *  $style->setTheme('ThemeName');
 *
 * или
 *  new Style('Themes');
 *  Style::setTheme('ThemeName');
 *
 *
 * Задается стиль для элемента Qt достаточно просто
 *
 * $label = new QLabel($this);
 * $label->styleSheet = Style::get('FileName');
 *
 * или
 *
 * $label = new QLabel($this);
 * Style::set($label, 'FileName');
 *
 */

/**
 * Class Style
 * @date    20.04.2016
 * @version 0.2
 *
 * Изменена логика получения стилей темы. Теперь тема получается только тогда когда была задана через метод setTheme().
 * Все остальные темы просто не считываются а заносятся в список достпуных тем оформления
 *
 * Так же добавлена возможность создавать дефолтовые темы оформления для приложения используя ресурсы приложения.
 * Данный подход позволяет обеспечить сохранность и неизменность дефолтовых тем оформления для приложения.
 * Для создания дефолтовых тем приложения необходимо создать в директории res проекта каталог с таким же названием как у каталога,
 * в котором будут хранится темы приложения вместе с пользовательскими темами.
 * В каталоге создать файл styleSheet.json
 *
 * Структура файла:
 * {
    "ThemeName1" : [
        "FileName1",
        "FileName2"
    ],
    "ThemeName2" : [
        "FileName1",
        "FileName2"
    ]
 * }
 *
 * Добавлена возможность создавать описание тем оформления, размещением файла about.json в директории темы
 * Структура файла в данный момент остается на усмотрение разработчика
 *
 * Добавлен метод about(), который возвращает информацию о теме оформелния в виде массива.
 * Если аргумент не передан то будет возвращена информация о теме оформления заданой через метод setTheme()
 * Если у темы отсутствует файл about.json то будет возвращен пустой массив
 *
 * Добавлен метод aboutAll(), который возвратит описание всех доступных тем у которых был в наличии файл about.json
 *
 * Метод setTheme() обзавелся алиасом setSkin()
 * Метод getThemesList() обзавелся алиасом getSkinsList()
 *
 */

/**
 * Class Style
 * @date    02.06.2016
 * @version 0.3
 *
 * Исправлено объявление объектов QDir и QFile так как они не корректно уничтожались из-за чего возникало падение приложения
 */
class Style {
    /** Переменная отвечающая за тему по умочанию */
    static private $theme;
    /** Массив стилей тем */
    static private $styles = array();
    /** Массив содержащий информацию о темах оформления */
    static private $about = array();
    /** Древовидная струтура тем оформления */
    static private $tree = array();
    /** Путь к директории расположения тем оформления */
    static private $path;
    /** Директория в ресурсах приложения аналогичная директории раположения тем оформления */
    private $resource;
    /** Объект QDir расширения PQEngine File System */
    static private $dir;
    /** Объект QFile расширения PQEngine File System */
    static private $file;

    /**
     * Style constructor.
     * @param $path - Дочерняя директория приложения в которой находятся темы
     */
    public function __construct($path) {
        /** Проверяем на зависимости */
        $this->checkDepend();
        /** Задаем директорию */
        $this->setPath($path);
        /** Делаем проверку на дефолтовые темы оформления */
        $this->copyDefault();
        /** Получаем список диреторий тем оформления и их файлов */
        $this->getStyles();
    }

    /**
     * Метод checkDepend() - Проверяет на наличие зависимостей
     */
    private function checkDepend() {
        /** Проверяем наличие подключеного расширения PQEngineFS */
        if(!class_exists('QDir') && !class_exists('QFile')) {
            /** Выводим сообщение о ошибке и завершаем работу приложения */
            die("Error!\r\nNeed to build the project with extension \"PQEngineFS\"!");
        } else {
            /** Инициализируем объекты расширения */
            self::$dir = new QDir();
            self::$file = new QFile();
        }
    }

    /**
     * Метод setPath() - Задает директрою в которой находятся темы и их стили
     * @param $path - Название дочерней директории приложения
     */
    private function setPath($path) {
        /** Проверяем на корректность переданый аргумент */
        if(!is_string($path) || empty($path)) {
            /** Если аргумент не является строкой или передана пустая строка то выводим сообщение о ошибке и завершаем работу приложения */
            die("Error!\r\nIt is necessary to specify a child directory in which styles of application are stored!");
        } else {
            /** Задаем директорию в которой хранятся темы стилей для приложения */
            self::$path = qApp::applicationDirPath()."/$path";
            $this->resource = $path;
        }
    }

    /**
     * Метод getStyles() - Проходится по дочерней директории и получает все темы с их стилями
     */
    private function getStyles() {
        /** Задаем директорию для поиска Тем приложения */
        self::$dir->setPath(self::$path);
        /** Проверяем директорию на существование */
        if(self::$dir->exists()) {
            /** Получаем список вложенных директорий без директорий "." и ".." */
            $themes = self::$dir->entryList(QDir::Dirs | QDir::NoDotAndDotDot);
            /** Проходим по вложенным директориям */
            foreach($themes as $theme) {
                self::$tree[$theme] = self::$path."/$theme";
                /** Задаем путь к файлу about.json темы оформления */
                self::$file->setFileName(self::$path."/$theme/about.json");
                if(self::$file->exists()) {
                    self::$file->open(QFile::ReadOnly);
                    self::$about[$theme] = json_decode(self::$file->readAll(), true);
                    self::$file->close();
                }
            }
        }
    }

    /**
     * Метод copyDefault() - Копирует дефолтовые темы оформления в директорию приложения
     * Перед копирование метод проверяет наличие файла в директории приложения
     * Если файл существует, то сравнивает контрольные суммы чтобы не перезаписывать файл при каждом запуске приложения
     * Если файл не существует, то копирует его из ресурсов приложения
     */
    private function copyDefault() {
        /** Проверяем находится ли конфигурационный файл в ресурсах приложения или нет */
        self::$file->setFileName(":/$this->resource/styleSheet.json");
        if(self::$file->exists()) {
            self::$file->oprn(QFile::ReadOnly);
            /** Поручаем данные из файла конфигурации назходящегося в ресурсах приложения */
            $default = json_decode(self::$file->readAll(), true);
            self::$file->close();
        } else {
            /** Если файл конфигураци нет в ресурсах приложения то ищем его в директории приложения */
            self::$file->setFileName("/$this->resource/styleSheet.json");
            if(self::$file->exxists()) {
                self::$file->oprn(QFile::ReadOnly);
                /** Поручаем данные из файла конфигурации назходящегося в ресурсах приложения */
                $default = json_decode(self::$file->readAll(), true);
                self::$file->close();
            } else {
                /** Если файла конфигурации нет в диреткории приложения, то задаем дефолтовый массив как пустой */
                $default = array();
            }
        }
        /** Проверяем данные из файла на пустоту */
        if(!empty($default)) {
            /** Проходим по массиву данных */
            foreach($default as $theme => $files) {
                /** Задаем путь к диретории темы оформления */
                self::$dir->setPath(self::$path."/$theme");
                /** Проверяем директорию на существование, если ее нет то создаем ее */
                if(!self::$dir->exists()) self::$dir->mkdir(self::$path."/$theme");
                /** Проходим по списку файлов темы оформления */
                foreach($files as $filename) {
                    /** Задаем путь к файлу */
                    self::$file->setFileName(self::$path."/$theme/$filename");
                    /** Проверяем существует ли файл в директории */
                    if(self::$file->exists()) {
                        /** Если существует, то получаем котрольную сумму файла из ресурсов приложения */
                        $resHash = hash_file('md5', "qrc://$this->resource/$theme/$filename");
                        /** Получаем контрольную сумму файла из диретории темы оформления */
                        $appHash = hash_file('md5', self::$path . "/$theme/$filename");
                        /** Сравниваем контрольные суммы */
                        if($resHash !== $appHash) {
                            /** Если котнрольные суммы не совпадают, то перезаписываем файл файлом из ресурсов */
                            self::$file->open(QFile::WriteOnly);
                            self::$file->write(file_get_contents("qrc://$this->resource/$theme/$filename"));
                            self::$file->close();
                        }
                    } else {
                        /** Если файла не сущетсвует, то копируем файл из ресурсов */
                        self::$file->open(QFile::WriteOnly);
                        self::$file->write(file_get_contents("qrc://$this->resource/$theme/$filename"));
                        self::$file->close();
                    }
                }
            }
        }
        /** Освобождаем временные переменнные */
        unset($default);
    }

    /**
     * Метод setTheme() - Задает тему для корректного получения стилей
     * @param $name - Название темы
     */
    static public function setTheme($name) {
        /** Проверяем переданный аргумент на корректность */
        if(is_string($name) && !empty($name)) {
            /** Задаем название Темы */
            self::$theme = $name;
            /** Задаем директорию Темы, стили которой необходимо получить */
            self::$dir->setPath(self::$tree[$name]);
            /** Проверяем директорию на существование */
            if(self::$dir->exists()) {
                $file = new QFile();
                /** Получаем списко файлов с расширением .qss */
                $styles = self::$dir->entryList(QDir::Files | '*.qss');
                /** Обрабатываем список файлов */
                foreach($styles as $style) {
                    /** Задаем путь к файлу для работы */
                    self::$file->setFileName(self::$tree[$name]."/$style");
                    /** Открываем файл только для чтения */
                    self::$file->open(QFile::ReadOnly);
                    /** Записываем данные из файла в массив темы */
                    self::$styles[$name][str_replace('.qss', '', $style)] = self::$file->readAll();
                    /** Закрываем файл */
                    self::$file->close();
                }
                /** Освобождаем временные переменные */
                unset($styles);
            }
        }
    }

    /**
     * Метод setSkin() - Алиас метода setTheme()
     * @param $name - Название темы
     */
    static public function setSkin($name) {
        self::setTheme($name);
    }

    /**
     * Метод get() - Возвращает стиль темы по имени
     * @param $name - Название стиля
     * @return string - Возвращает строку стилей qss
     */
    static public function get($name) {
        /** Проверяем существование темы и стиля и возвращаем в противном случае возвращаем пустую строку */
        return (isset(self::$styles[self::$theme][$name]) ? self::$styles[self::$theme][$name] : '');
    }

    /**
     * Метод set() - Задает стиль переданному объекту Qt
     * @param $object - Объект Qt которому необходимо задать стиль
     * @param $name - Название файла стиля
     */
    static public function set(&$object, $name) {
        /** Проверяем существование темы и стиля и задаем объекту в противном случае задаем пустую строку */
        $object->styleSheet = (isset(self::$styles[self::$theme][$name]) ? self::$styles[self::$theme][$name] : '');
    }

    /**
     * Метод getThemesList() - Возвращает список всех доступных тем оформления
     * @return array - Массив содержащий все навзния тем оформлений
     */
    static public function getThemesList() {
        $list = array();
        foreach(self::$tree as $theme => $value) $list[] = $theme;
        return $list;
    }

    /**
     * Метод getSkinsList() - Алиас метода getThemesList()
     * @return array
     */
    static public function getSkinsList() {
        return self::getThemesList();
    }

    /**
     * Метод about() - Метод который выводи информацию о теме оформления
     * @param string $name - Название темы оформления
     * @return array - Массив данных
     */
    static public function about($name = '') {
        /** Проверяем является ли аргумент строкой */
        if(is_string($name)) {
            /** Если является то проверяем строку на пустоту */
            if(empty($name)) {
                /**
                 * Если строка пуста, то пытаемся вернуть данные
                 * о теме оформления которая задана через метод setTheme() или setSkin()
                 * Если данных нет то возвращаем пустой массив
                 */
                return (isset(self::$about[self::$theme]) ? self::$about[self::$theme] : array());
            } else {
                /**
                 * Если строка не пуста, то пытаемся вернуть данные
                 * о теме оформления имя которой передано
                 * Если данных нет то возвращаем пустой массив
                 */
                return (isset(self::$about[$name]) ? self::$about[$name] : array());
            }
        } else {
            /** Если аргумент не является строкой, то сразу возвращаем пустой массив */
            return array();
        }
    }

    /**
     * Метод aboutAll() - Возвращает данные о всех темах оформления в которых был файл about.json
     * @return array - Массив данных
     */
    static public function aboutAll() {
        return self::$about;
    }
}

/** Конец файла Style.php */