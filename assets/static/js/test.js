//فصل اول - بخش اول
// var test1 = "hamyarjs";
// test1 = "hamyar";
// var test2 = "alireza";
// var test3 = "roshan";
// var $test4 = "program";
// var userName = "program1";
// let user = "ali";
// const example = 1403;
// example = 1404;
// console.log(example);




//فصل اول - بخش دوم
// let data = false;
// console.log(typeof x);
//5 + 2 = 7
//5 * 2 = 10
// let x = 5;
// let y = 2;
// let z = x + y;
// console.log("the sum of x + y is:" + z);
// let x = 10;
// x++;
// console.log(x);
// let number = 30;
// let number2 = 35;
//"20" ==> data: string  value:20
//20 ===> data:number value:20
// if(number > 20){
//     console.log("دستورات داخل if اجرا میشه");
// }else if(number2 == 35){

// }else{
//     console.log("دستورات داخل else اجرا میشه");
// }
// let age = 19;
// let result = (age < 18 ) ? "young" : "old";
// console.log(result); 
// let x = 6;
// switch(x){
//  case 5:
//     console.log("مورد 5");
//     break;
//  case 7:
//     console.log("مورد 7");
//     break; 
//  case 8:
//     console.log("مورد 8");
//     break; 
//  default:
//     console.log("مورد خاصی رو پیدا نکردم"); 
//}



//فصل اول - بخش سوم
// document.getElementById("demo").style.color = "red";
// //<h1 style="color:red"></h1>
// document.getElementsByClassName("demo2")[0].innerHTML = "مرجع تخصصی";
// document.getElementsByClassName("demo2")[3].innerHTML = "تگ هدینگ آخری";

// document.getElementsByTagName("h3")[0].style.color = "blue";
// document.getElementsByTagName("h3")[1].style.color = "red";
// document.getElementsByTagName("h3")[2].textContent = "jquery";

// let text = document.getElementById("myText").textContent;
// console.log(text);
// let text2 = document.getElementById("myText").innerText;
// console.log(text2);
// let text3 = document.getElementById("myText").innerHTML;
// console.log(text3);



//فصل اول - بخش چهارم
//for
//for/In
//for/of
// for(part1;part2;part3){
// //stam
// }
// for(let i = 0;i < 5;i++){
//   console.log(i);
// }

// function showMassage(name){
//   console.log(name);
// }
// function myFunction(p1,p2){
//   return p1 * p2;
// }
// let result = myFunction(4,3);
// console.log(result);


//فصل اول - بخش پنجم
// let test = document.getElementById("tagA").getAttribute("id");
// console.log(test);
// function check(){
//   let MyBox ,TagP;
//   MyBox = document.getElementById("MyBox");
//   TagP = document.getElementById("MyP");
//   if(MyBox.contains(TagP)){
//     console.log("بله هستش");
//   }else{
//     console.log("نه نیستش");
//   }
// }
// let MyBox = document.getElementById("MyBox");
// console.log(MyBox.classList);
// function Add(){
//     let MyBox = document.getElementById("myH1");
//     MyBox.classList.add("hamyarjs");
// }
// function Del(){
//     let MyBox = document.getElementById("myH1");
//     MyBox.classList.remove("hamyarjs");
// }



//فصل اول - بخش ششم
// let name = "ali";
// let family = "roshan";
// let age = 27;
// let person = ["ali","roshan",27];
// console.log(person[0]);
//روش اول
//let person = ["ali","roshan",27];
//روش دوم
//let person = new Array("ali","roshan",27);
//روش سوم
// let person = [];
// person[0] = "ali";
// person[1] = "roshan";
// person[2] = 27;
// console.log(person);
// let person = ["ali","roshan",27];
// person[0] = "reza";
// console.log(person);
// let person = ["ali","roshan",27];
// console.log(person.length);
// let person = ["ali","roshan",27];
// person.push("tehran");
// console.log(person);

//فصل اول - بخش هفتم
// let text = "Hellow world, welcome to the universe";
// console.log(text.indexOf("e",5));
// let text = "Hellow world, welcome to the universe";
// console.log(text.split("",3));
// let text = "Hellow world, welcome to the universe";
// console.log(text.substring(5));
// let text = "Hellow world, welcome to the universe";
// console.log(text.replace("world","ali"));
// let name = "ali";
// // console.log("my name is " + name);
// console.log(`my name is ${name}`);

//فصل اول - بخش هشتم
// let id = 1;
// let name = "ali";
// let family = "roshan";
// let age = 27;
// let phone = 09362405901;

// let id2 = 2;
// let name2 = "reza";
// let family2 = "roshan2";
// let age2 = 30;
// let phone2 = 056465465564;

// let person = [1,"ali","roshan",27,09362405901,2,"reza","roshan2",056465465564];
// console.log(person[1]);
//روش اول برای ایجاد و تعریف ابجکت
//id:1 ===> kay:value
// let person = {
//     id:1,
//     name:'alireza',
//     family:'roshan',
//     age:27
// };
// let users = [
//   {id:1, name:'alireza',family:'roshan',age:27},
//   {id:2, name:'ali',family:'roshan2',age:37}
// ];
//روش دوم برای تعریف و ایجاد ابجکت 
// let daneshjoo = new Object();
// daneshjoo.name = "ali";
// daneshjoo.family = "roshan";
// daneshjoo.age = 25;
//روش سوم برای تعریف و ایجاد ابجکت
// function daneshjoo(name,family,age){
//   this.name = name;
//   this.family = family;
//   this.age = age;
// }
// let myDaneshjoo = new daneshjoo("ali","roshan",25);
//نمایش یا فراخوانی آیتم های شی
// let person = {
//         id:1,
//         name:'alireza',
//         family:'roshan',
//         age:27
//     };
//     //روش اول
//     console.log(person.family);
//     //روش دوم
//     console.log(person["age"]);
//نحوه اضافه کردن ویژگی به شی
// let person = {
//         id:1,
//         name:'alireza',
//         family:'roshan',
//         age:27
//     };
//     person.phone = '09362405901';
//     console.log(person);
//نحوه حذف کردن ویژگی از شی
// let person = {
//         id:1,
//         name:'alireza',
//         family:'roshan',
//         age:27
//     };
//     delete person.age;
//     console.log(person);
//عبارت با قاعده
// /pattern/flags
// let text = "my name is Alireza";
// console.log(text.replace(/alireza/i,"reza"));


//فصل اول - بخش نهم
// let d = new Date();
// let result1 = d.getDate();
// let result2 = d.getHours();
// let result3 = d.getFullYear();
// console.log(result3);
// let date = Date.now();
// //console.log(date);
// let dateFormat = new Intl.DateTimeFormat('fa-IR').format(date);
// console.log(dateFormat);
// //1402 آذر 5 , یکشنیه
// let dateFormat2 = new Intl.DateTimeFormat('pt-BR',{
//     weekday:"long",
//     year:"numeric",
//     month:"long",
//     day:"numeric",
//     hour:"2-digit",
//     minute:"2-digit"
// }).format(date);
// console.log(dateFormat2);


//فصل اول - بخش دهم
//es6 es7 es8 es9 . . . 
// var x = "this is a book";
// function test(){
//     console.log(x);
// }
// test();
// function test2(){
//     var x = 1;
//     console.log(x);
// }
// test2();
// var name = "ali";
// var name = "reza";
// console.log(name);

// console.log(name);
// var name = "reza";

// var greeter = "Hi";
// var times = 4;

// if(times > 3){
//   var greeter = "hello";
// }
// console.log(greeter);

// let greeter = "Hi";
// let times = 4;

// if(times > 3){
//   let greeter = "hello";
//   console.log(greeter);
// }
// console.log(greeter);

// let x = 25;
// x = 27;

// console.log(x);
// let x = 20;

// const p = 10;
// p = 15;
// console.log(p);

// const person = {
//     name: "alireza",
//     family:"roshan"
// }
// person.name = "reza";
// console.log(person);

// const person = {
//     name: "alireza",
//     family:"roshan"
// }
// const person = {
//     name: "reza",
//     family:"roshan2"
// }

// console.log(person);