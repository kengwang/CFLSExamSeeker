<?php

/**
 * Curl访问请求集成,自动转化为Array
 * @param  $url string 访问链接
 * @param $json bool 是否将返回结果转化为Array
 * @param $ispost bool 是否为post,否则为get
 * @param $post array Post/Get的数据用Array
 * @return  array JSON
 */
function cquery($url, $json, $ispost, $post, $getinurl = false)
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
            $getdata = http_build_query($post);
            $url = $url . "?" . $getdata;
        }
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //如果把这行注释掉的话，就会直接输出

    $result = curl_exec($ch);
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
        echo '请求错误:';
        print_r($bak);
        exit;
    }
    return $bak['StudentModel']['StudentID'];
}

function getStuInfo($stun)
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
        exit;
    }
    $student = $bak['StudentModel'];
    $info = '学生姓名:' . $student['StudentName'] . PHP_EOL . '家长手机:' . $student['GuardianPhone'] . PHP_EOL . '班级:' . $student['ClassName'] . PHP_EOL.'住址:'.$student['HomeAddress'].PHP_EOL . '班主任:' . $student['ClassHeaderName'] . '(' . $student['ClassHeaderPhone'] . ')' . PHP_EOL . '年级主任: ' . $student['GradeHeaderName'] . '(' . $student['GradeHeaderPhone'] . ')';
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
        echo PHP_EOL . '[' . $n . '] => ' . $exam['ExamsName'] . ' ( 总分: ' . $exam['TotalScore'] . ' 班排:' . $exam['ClassRanking'] . ' 年排:' . $exam['GradeRanking'] . ')';
        $examids[$n] = array('id' => $exam['ExamsID'], 'name' => $exam['ExamsName'], 'score' => $exam['TotalScore'], 'classrank' => $exam['ClassRanking'], 'graderank' => $exam['GradeRanking']);
        $n++;
    }
    echo PHP_EOL . '输入前面框框内数字查询当次详情: ';
    $handle = fopen("php://stdin", "r");
    $s = fgets($handle);
    $s = intval($s);
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
    echo PHP_EOL . '录入时间: ' . $lists[0]['ExamsStartDate'] . '  总分: ' . $exam['score'] . '  班排: ' . $exam['classrank'] . '  年排: ' . $exam['graderank'];
    echo PHP_EOL . '单科成绩: ';
    foreach ($lists as $list) {
        echo PHP_EOL . '科目: ' . $list['SubjectName'] . '  分数: ' . $list['Score'] . '  班排:  ' . $list['ClassRanking'] . '  年排:  ' . $list['GradeRanking'];
    }
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
        if ($teacher['TeacherName'] == '成外') { //没有该课老师
            //$info = $info . PHP_EOL . '没有' . $teacher['SubjectName'];
        } else {
            $info = $info . PHP_EOL . $teacher['SubjectName'] . ' : ' . $teacher['TeacherName'] . '(' . $teacher['MobilePhone'] . ')';
        }
    }
    return $info;
}


if (isset($_GET['stun'])){
    echo PHP_EOL . 'Github项目地址: https://github.com/kengwang/CFLSExamSeeker' . PHP_EOL;
    $stun = $_GET['stun'];
    $stuid = ID2StudentID($stun);
    echo '转换学号为ID: ' . $stuid . PHP_EOL;    
}

if (isset($_GET['getinfo'])){
    echo getStuInfo($stun);
}

if (isset($_GET['getteacher'])){
    echo getTeacherFormat($stun);
}

if (isset($_GET['test'])){
    $time=$_GET['test'];
    if (date('Y-m-d', strtotime($time)) != $time) {
        echo '请输入合法的时间 xxxx-xx-xx';
        exit;
    }
    getRecentScore($stuid, $argv[$n + 1]);
}

for ($n = 0; $n < $argc; $n++) {
    switch ($argv[$n]) {
        case '--stun':
            echo PHP_EOL . '您需要同意: ' . PHP_EOL . '* 不要在未经过他人允许的情况下查询' . PHP_EOL . '* 不要恶意爬虫他人信息' . PHP_EOL . '* 恶意使用造成的后果作者不负责' . PHP_EOL . '同意输入[1] 不同意按[Ctrl]+[c]退出';
            $handle = fopen("php://stdin", "r");
            $s = fgets($handle);
            if (intval($s) != 1) {
                exit;
            }
            $stun = $argv[$n + 1];
            $stuid = ID2StudentID($stun);
            echo '转换学号为ID: ' . $stuid . PHP_EOL;
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
        case '-h':
        case '--help':
        case '?':
            echo PHP_EOL . 'Github项目地址: https://github.com/kengwang/CFLSExamSeeker' . PHP_EOL;
            echo '作者: Kengwang 请不要恶意查分&爬虫';
            echo  '--stun 学号 [必须]' . PHP_EOL;
            echo '--getinfo 获取学生信息' . PHP_EOL;
            echo '--getteacher 获取老师信息' . PHP_EOL;
            echo '--test <开始时间> 获取考试信息' . PHP_EOL;
            break;
    }
}
?>