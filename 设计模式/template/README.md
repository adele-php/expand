## 模板方法模式

* 1.模板方法模式的定义
    >定义一个操作中的算法框架，而将一些步骤延迟到子类。使得子类可以不改变一个算法的结构即可重定义该算法的某些特定步骤
    
* 2.AbstractClass 抽象模板
    >基本方法：由子类实现,并在模板方法中调用
    >模板方法：实现对基本方法的调度，完成固定的逻辑


    
#### 应用场景

* 多个子类由公有的方法，并且逻辑基本相同时
* 重要、复杂的算法，可以把核心算法设计成模板方法，周边的相关细节功能由子类实现
* 重构时，把相同的代码抽取到父类，然后通过钩子函数约束行为