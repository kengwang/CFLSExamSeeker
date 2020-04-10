<?php

if (isset($_GET['stun'])) {
    define('NEWLINE', '<br>');
} else {
    define('NEWLINE', PHP_EOL);
}

/**
 * Curl访问请求集成,自动转化为Array
 * @param  $url string 访问链接
 * @param $json bool 是否将返回结果转化为Array
 * @param $ispost bool 是否为post,否则为get
 * @param $post array Post/Get的数据用Array
 * @return  array JSON
 */
function cquery($url, $json, $ispost, $post = null, $getinurl = false, $debug = false)
{
    $ch = curl_init();
    if ($ispost) {
        //设置post方式提交
        curl_setopt($ch, CURLOPT_POST, 1);
        //设置post数据
        //$post_data = JSON($post);   这个是专门为了微信API设计
        $post_data = http_build_query($post);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    } else {
        if (!$getinurl) {
            if ($post !== null) {
                $getdata = http_build_query($post);
                $url = $url . "?" . $getdata;
            }
        }
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    if (!$debug) curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //如果把这行注释掉的话，就会直接输出

    $result = curl_exec($ch);
    if ($debug) {
        echo 'cUrl is Debugging' . NEWLINE;
        if ($result == false) {
            echo 'The URL is : "' . $url . '"' . NEWLINE;
            echo 'Curl error: ' . curl_errno($ch) . NEWLINE;
        }
    }
    curl_close($ch);
    if ($json) {
        $result = json_decode($result, true);
    }
    return $result;
}

function ID2StudentID($id)
{
    $arr = array(
        'SchoolCode' => 'cdwgy01',
        'StudentNumber' => $id
    );
    $bak = cquery('http://118.114.237.224:88/Partner/StudentfromID', true, true, $arr);
    if ($bak['Result'] != 1) {
        echo '学号' . $id . '请求错误:';
        print_r($bak);
        exit;
        return null;
        //exit;
    }
    return $bak['StudentModel']['StudentID'];
}

function getStuInfo($stun, $raw = false)
{
    if (!is_numeric($stun)) {
        echo "--get-info 参数应为学号!";
    }
    $arr = array(
        'SchoolCode' => 'cdwgy01',
        'StudentNumber' => $stun
    );
    $bak = cquery('http://118.114.237.224:88/Partner/StudentfromID', true, true, $arr);
    if ($bak['Result'] != 1) {
        echo '请求错误';
        print_r($bak);
    }
    if ($raw) {
        return $bak;
    }
    $student = $bak['StudentModel'];
    $info = '学生姓名:' . $student['StudentName'] . NEWLINE . '家长手机:' . $student['GuardianPhone'] . NEWLINE . '班级:' . $student['ClassName'] . NEWLINE . '住址:' . $student['HomeAddress'] . NEWLINE . '班主任:' . $student['ClassHeaderName'] . '(' . $student['ClassHeaderPhone'] . ')' . NEWLINE . '年级主任: ' . $student['GradeHeaderName'] . '(' . $student['GradeHeaderPhone'] . ')';
    return $info;
}

function getRecentScore($stuid, $startdate)
{
    if (empty($stuid)) {
        echo '请使用 --stun 输入学号';
        exit;
    }
    $url = 'http://118.114.237.224:88/_APP/Execute?id=Func=Exam@@@StartDate=' . $startdate . '@@@EndDate=' . date('Y-m-d') . '@@@SchoolNO=cdwgy01@@@StudentNO=' . $stuid;
    $bak = cquery($url, true, false, $url, true);
    if ($bak['result'] == 0) {
        print_r($bak);
        exit;
    }
    $exams = $bak['ListTable'];
    $n = 0;
    foreach ($exams as $exam) {
        echo NEWLINE . '[' . $n . '] => ' . $exam['ExamsName'] . ' ( 总分: ' . $exam['TotalScore'] . ' 班排:' . $exam['ClassRanking'] . ' 年排:' . $exam['GradeRanking'] . ')';
        $examids[$n] = array('id' => $exam['ExamsID'], 'name' => $exam['ExamsName'], 'score' => $exam['TotalScore'], 'classrank' => $exam['ClassRanking'], 'graderank' => $exam['GradeRanking']);
        $n++;
    }
    if (!isset($_GET['examn'])) {
        echo NEWLINE . '输入前面框框内数字查询当次详情: ';
        $handle = fopen("php://stdin", "r");
        $s = fgets($handle);
        $s = intval($s);
    } else {
        $s = $_GET['examn'];
    }
    $exam = $examids[$s];
    $examid = $examids[$s]['id'];
    echo $examids[$s]['name'] . ' (' . $examid . ')';
    $url = 'http://118.114.237.224:88/_APP/Execute?id=Func=ExamResult@@@SchoolNO=cdwgy01@@@StudentNO=' . $stuid . '@@@ExamsID=' . $examid;
    $bak = cquery($url, true, false, $examids, true);
    if ($bak['result'] == 0) {
        print_r($bak);
        exit;
    }
    $lists = $bak['ListTable'];
    echo NEWLINE . '录入时间: ' . $lists[0]['ExamsStartDate'] . '  总分: ' . $exam['score'] . '  班排: ' . $exam['classrank'] . '  年排: ' . $exam['graderank'];
    echo NEWLINE . '单科成绩: ';
    foreach ($lists as $list) {
        echo NEWLINE . '科目: ' . $list['SubjectName'] . '  分数: ' . $list['Score'] . '  班排:  ' . $list['ClassRanking'] . '  年排:  ' . $list['GradeRanking'];
    }
}

function GetExamCSVByClass($examid, $class, $grade = 2019, $maxclass = 25, $minclass = 1)
{
    if ($class == 'for') {
        for ($i = $minclass; $i <= $maxclass; $i++) {
            RealGetScores(($grade * 10000) + ($i * 100), $examid);
        }
    } else {
        RealGetScores($class * 100, $examid);
    }

    echo "输出完成了";
}

function RealGetScores($class, $examid)
{
    $realclass = intval($class / 100) % 100;
    echo 'Getting class ' . $realclass . NEWLINE;
    $first = true;
    $csv = fopen("exam.csv", "a");
    fwrite($csv, "\xEF\xBB\xBF"); //utf8支持
    for ($i = $class + 1; $i <= $class + 60; $i++) {
        echo '学号: 0' . $i . NEWLINE;
        $stuinfo = getStuInfo('0' . $i, true);
        $stuid = $stuinfo['StudentModel']['StudentID'];
        if ($stuid !== null) {
            $url = 'http://118.114.237.224:88/_APP/Execute?id=Func=ExamResult@@@SchoolNO=cdwgy01@@@StudentNO=' . $stuid . '@@@ExamsID=' . $examid;
            $bak = cquery($url, true, false, $url, true, false);
            if ($bak['result'] == false) {
                var_dump($bak);
                continue;
            }
            $lists = $bak['ListTable'];
            $subject = array();
            $subject[] = "学号";
            $subject[] = "班级";
            $subject[] = "姓名";
            $score = array(
                0 => $stuinfo['StudentModel']['StudentNumber'],
                1 => $realclass,
                2 => $stuinfo['StudentModel']['StudentName']
            );
            foreach ($lists as $list) {
                //echo NEWLINE . '科目: ' . $list['SubjectName'] . '  分数: ' . $list['Score'] . '  班排:  ' . $list['ClassRanking'] . '  年排:  ' . $list['GradeRanking'];
                if ($first) {
                    $subject[] = $list['SubjectName'];
                }
                $score[] = $list['Score'];
            }
            if ($first) {
                fputcsv($csv, $subject);
                $first = false;
            }

            fputcsv($csv, $score);
        }
    }
    fclose($csv);
}

function getTeacherFormat($stun)
{
    if (!is_numeric($stun)) {
        echo "未指定学号!";
        exit;
    }
    $arr = array(
        'SchoolCode' => 'cdwgy01',
        'StudentNumber' => $stun
    );
    $bak = cquery('http://118.114.237.224:88/Partner/StudentfromID', true, true, $arr);
    if ($bak['Result'] != 1) {
        echo '请求错误';
        print_r($bak);
        exit;
    }
    $teachers = $bak['StudentModel']['ListSubject'];
    $info = '';
    foreach ($teachers as $teacher) {
        if (!$teacher['TeacherName'] == '成外') { //没有该课老师
            $info = $info . NEWLINE . $teacher['SubjectName'] . ' : ' . $teacher['TeacherName'] . '(' . $teacher['MobilePhone'] . ')';
        }
    }
    return $info;
}

if (isset($_GET['csv'])) {
    if (isset($_GET['maxclass'])) {
        GetExamCSVByClass($_GET['examid'], 'for', $_GET['grade'], $_GET['maxclass'], $_GET['minclass']);
    } else {
        GetExamCSVByClass($_GET['examid'], $_GET['class'], $_GET['grade']);
    }
    exit;
}

if (isset($_GET['stun'])) {
    echo NEWLINE . 'Github项目地址: https://github.com/kengwang/CFLSExamSeeker' . NEWLINE;
    $stun = $_GET['stun'];
    $stuid = ID2StudentID($stun);
    echo '转换学号为ID: ' . $stuid . NEWLINE;
}

if (isset($_GET['getinfo'])) {
    echo getStuInfo($stun);
}

if (isset($_GET['getteacher'])) {
    echo getTeacherFormat($stun);
}

if (isset($_GET['test'])) {
    $time = $_GET['test'];
    if (date('Y-m-d', strtotime($time)) != $time) {
        echo '请输入合法的时间 xxxx-xx-xx';
        exit;
    }
    getRecentScore($stuid, $time);
}

for ($n = 0; $n < $argc; $n++) {
    switch ($argv[$n]) {
        case '--stun':
            echo NEWLINE . '您需要同意: ' . NEWLINE . '* 不要在未经过他人允许的情况下查询' . NEWLINE . '* 不要恶意爬虫他人信息' . NEWLINE . '* 恶意使用造成的后果作者不负责' . NEWLINE . '同意输入[1] 不同意按[Ctrl]+[c]退出';
            $handle = fopen("php://stdin", "r");
            $s = trim(fgets($handle));
            if (intval($s) != 1) {
                exit;
            }
            $stun = $argv[$n + 1];
            $stuid = ID2StudentID($stun);
            echo '转换学号为ID: ' . $stuid . NEWLINE;
            break;
        case '--getinfo':
            echo getStuInfo($stun);
            break;
        case '--getteacher':
            echo getTeacherFormat($stun);
            break;
        case '--test':
            if (date('Y-m-d', strtotime($argv[$n + 1])) != $argv[$n + 1]) {
                echo '请输入合法的时间 xxxx-xx-xx';
                exit;
            }
            getRecentScore($stuid, $argv[$n + 1]);
            break;
        case '--gradeexam':
            echo '请输入年级: ';
            $handle = fopen("php://stdin", "r");
            $grade = trim(fgets($handle));
            echo '请输入考试号: ';
            $handle = fopen("php://stdin", "r");
            $examid = trim(fgets($handle));
            echo '请输入最大班级数: ';
            $handle = fopen("php://stdin", "r");
            $maxclass = trim(fgets($handle));
            echo '请输入最小班级数: ';
            $handle = fopen("php://stdin", "r");
            $minclass = trim(fgets($handle));
            GetExamCSVByClass($examid, 'for', $grade, $maxclass, $minclass);
            break;
        case '--classexam':
            echo '请输入班级: ';
            $handle = fopen("php://stdin", "r");
            $class = trim(fgets($handle));
            echo '请输入考试号: ';
            $handle = fopen("php://stdin", "r");
            $examid = trim(fgets($handle));
            GetExamCSVByClass($examid, $class);
            break;
        case '-h':
        case '--help':
        case '?':
            echo NEWLINE . 'Github项目地址: https://github.com/kengwang/CFLSExamSeeker' . NEWLINE;
            echo '作者: Kengwang 请不要恶意查分&爬虫' . NEWLINE;
            echo '--stun 学号 [必须]' . NEWLINE;
            echo '--getinfo 获取学生信息' . NEWLINE;
            echo '--getteacher 获取老师信息' . NEWLINE;
            echo '--test <开始时间> 获取考试信息' . NEWLINE;
            break;
    }
}
