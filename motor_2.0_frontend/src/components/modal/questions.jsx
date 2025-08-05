import React from "react";
import { BsArrowReturnRight } from "react-icons/bs";

export const generalQuestions = [
  {
    id: "faq1",
    question: "What is car insurance?",
    answer:
      "A car insurance policy covers the financial liability which arises in case the insured car injures any third party life or damages any third party property. Moreover, if the coverage of the policy is comprehensive, coverage is also given for the damages suffered by the car due to accidents or any other calamities. A car insurance policy is mandatory as per the rules of the Motor Vehicles Act, 1988. Only if the car has a valid insurance cover, it is allowed to run on Indian roads. The car owner is the policy holder who is required to pay the premium on an annual basis.",
  },
  {
    id: "faq2",
    question: "Why should I buy car insurance?",
    answer:
      "A car insurance policy is mandatory as per the rules of the Motor Vehicles Act, 1988. Thus, to legally drive your car in India, you need to have at least a third party car insurance policy. Moreover, the policy will provide financial assistance in case of third party property damages caused by your car in case of accidents. In case an individual suffers an injury or dies due to the car, you can face a considerable financial liability for the loss caused. The policy also covers this liability and gives you financial relief. Moreover, opting for a comprehensive car insurance policy will also help cover the damages suffered by your car in case of accidents or theft. Such policies pay for the repair of the car or compensate your financially in case of thefts.",
  },
  {
    id: "faq3",
    question: "What are the advantages of having a Car Insurance Policy?",
    answer: (
      <span>
        Following are some of the advantages of having an insurance policy for
        your car: {"\n"}
        <BsArrowReturnRight /> In case of any accident involving your car, if
        you injure or kill any individual or damage someone’s property, you
        would be financially liable for the injury or damage caused. In case of
        accidental death, the liability is quite high. This however gets covered
        by a car insurance policy. {"\n"}
        <BsArrowReturnRight /> If your car faces damages in an accident, the
        repair costs are quite high. The more expensive the car is, the higher
        would be its expenses. This might burn a hole in your pocket. If you buy
        a comprehensive car insurance plan such repair costs are also covered.{" "}
        {"\n"}
        <BsArrowReturnRight /> A car insurance plan is a mandatory requirement.
        If you don’t buy a plan, you face legal consequences which include heavy
        fines and might also lead to imprisonment. So, buying a car insurance
        plan is necessary in this context too. {"\n"}
      </span>
    ),
  },
  {
    id: "faq4",
    question:
      "What are the different types of car insurance policies available?",
    answer: (
      <span>
        Car insurance policies come in the following two types – {"\n"}
        <BsArrowReturnRight /> <b> Third party liability only policy – </b> this
        policy has a restricted scope of coverage. It covers only two instances
        of third party liability which might be faced by a car owner. Firstly,
        if the car causes injury or death of any individual other than the car
        owner, it gives rise to a third-party liability. The car owner is liable
        to compensate the injured individual or his family (in case of
        accidental death). The car insurance policy covers this financial
        liability. Second coverage is given against liability faced if there is
        any damage to the property belonging to a third party. Third party
        liability only policies, therefore, do not cover damages suffered by the
        car and its owner/driver. Third Party Insurance is mandatory as per
        Motor Vehicle Act 1988 and without this, it is illegal to drive a
        vehicle. Premiums for third party liability insurance are fixed by the
        IRDAI and they are very low, so buying a third party insurance is not a
        problem {"\n"}
        <BsArrowReturnRight /> <b> Comprehensive package policy – </b> It is an
        all-inclusive policy which is also known as a comprehensive coverage
        policy. The policy covers third party liability faced if you injure or
        kill any individual or damage any individual’s property. Along with the
        mandatory third party cover, the damages suffered by the car are also
        covered. If your car is damaged due to covered perils, then the repair
        costs incurred would get covered under the insurance policy.
        Furthermore, there is also a personal accident cover inbuilt in the
        plan. This cover provides compensation in case of death or disability
        suffered by the owner or driver of the insured car because of the
        accident. Premiums for this policy are higher because of a wider scope
        of coverage available under these policies. {"\n"}
        <BsArrowReturnRight /> <b> Standalone Own damage plan – </b>It provides
        protection against damages to your car in case of accidents, theft, fire
        or natural calamities similar to OD component in comprehensive policy.
        However this plan can be bought only if you already have an existing
        third party insurance cover valid for at-least a year. {"\n"}
      </span>
    ),
  },
  {
    id: "faq5",
    question: "Why is it necessary to compare car insurance policies?",
    answer: (
      <span>
        It is always recommended to compare car insurance policy before making a
        purchase. Following are some of the reasons to do so:{"\n"}
        <BsArrowReturnRight /> <b>Find the best plan - </b>There are a lot of
        car insurance plans available in the Indian insurance market. Each plan
        promises something better than the other. To know the difference between
        the different plans, you need to compare. In order to find the plan
        which is the best for your car based on such differences, comparing
        becomes essential.{"\n"}
        <BsArrowReturnRight /> <b>Choose a higher IDV - </b>the IDV stands for
        your car’s Insured Declared Value. This value reflects the market price
        of your car after being adjusted for depreciation. Different policies
        fix the IDV in a different manner. That is why you see different IDVs
        offered for the same car. Ideally, you should opt for the highest
        possible IDV to maintain the value of your car. This would help you get
        the maximum claim settlement from the company in case of theft or total
        loss of your car. The option of choosing a high IDV is possible only
        through comparing{"\n"}
        <BsArrowReturnRight /> <b>Get the best premium rates – </b>just like the
        IDV is different across different car insurance plans, the premiums too
        vary. Needless to say you would want the lowest possible premium for
        your car insurance policy. You can choose the best premium rate when you
        get to compare between the different plans before you buy one.
        {"\n"}
        <BsArrowReturnRight /> <b>Get extensive coverages – </b>the coverage
        features across different car insurance plans also vary. Some companies
        might have all the covers required for you while others might not offer
        so. If you want your plan to have an all-inclusive coverage you should
        compare different plans and choose a plan which has the required
        coverage features at the best rates of premiums.
        {"\n"}
        <BsArrowReturnRight /> <b>Avail higher discounts –</b> car insurance
        policies offer attractive rates of discounts which help in reducing your
        premium outgo. To search for the highest rate of discounts you have to
        compare because different plans allow different discount rates.{"\n"}
        <BsArrowReturnRight /> <b>Get cashless garage service–</b> Every car
        insurance company has a tie up with a number of garages across the
        country. However it is always recommended to go for the company that has
        an exhaustive network of such garages. You can hence compare which
        insurers have higher network garages available at your location.{"\n"}
      </span>
    ),
  },
];
export const CarInsCover = [
  {
    id: "cfaq1",
    question: "What is a Zero Depreciation cover?",
    answer:
      "In case of a car insurance claim, the costs incurred on repairing or replacing the damaged parts are reduced with appropriate depreciations incurred on the parts. As a result, the policy pays a reduced claim amount while you bear the cost of depreciation. Through this cover, the cost of depreciation becomes zero. The insurer settles the full claim value irrespective of the depreciation applicable on the plastic and metal parts of car. This cover is mostly available for cars of age up to 5 years. Also, when you are opting for this add on cover, you need to make sure this add on was available in your previous policy also. Else the insurer might ask for a vehicle inspection if there is a gap.",
  },
  {
    id: "cfaq2",
    question:
      "What is a Personal accident cover? Why is it included in car insurance?",
    answer:
      "A personal accident cover protects the owner driver of the car involved in an accident. In case of hospitalisation or accidental death, this cover will compensate for the financial losses incurred. Hence it is covered within a car insurance policy. Also, government has made it mandatory to have a compulsory personal accident cover of Rs 15 lakh along with car insurance policy.",
  },
  {
    id: "cfaq3",
    question: "What is a roadside assistance cover?",
    answer:
      "In this add-on cover, the insurance company gives you a 24*7 assistance if your car breaks down in the middle of the road and you can not reach the nearest garage. This also covers if you run out of fuel or tyres get punctured or deflated. You need to intimate your insurer about the problem and they will make necessary arrangements like onsite repairs, tyre changes, fuel tank fill ups, battery jump starts or towing facility in case of major damages",
  },
  {
    id: "cfaq4",
    question:
      "What is a No Claim Bonus? And what happens to NCB when a claim is made?",
    answer: (
      <span>
        No claim bonus is an incentive provided by the insurer in case you do
        not have any claims made in a policy year. The incentive is in the form
        of a discount given on the Own Damage component of a comprehensive car
        insurance policy. This discount can go upto 50% of the OD value. However
        NCB gets forfeited in following cases: {"\n"}
        <BsArrowReturnRight /> If there is any claim made {"\n"}
        <BsArrowReturnRight /> If there is a break in the insurance period for
        more than 90 days {"\n"}
      </span>
    ),
  },
  {
    id: "cfaq5",
    question: "What is NCB Protect cover?",
    answer:
      "You earn a No Claim discount in the year when you don’t make a car insurance claim. This discount accumulates for every claim free year and gets applied on the Own Damage component of your insurance premium at the time of renewal. However, a single instance of claim wipes out the accumulated discount. Through this add-on you can protect the applicable NCB discount even if you make a claim under your policy. The add on will not only protect your existing NCB but will help you take it to the next slab during renewal even though there was a claim made. This cover can be used for upto 3 claims depending on the insurance company.",
  },
];
export const carInsPrem = [
  {
    id: "mfaq1",
    question: "What is IDV and how is it calculated?",
    answer: (
      <span>
        The Insured's Declared Value (IDV) of the vehicle will be deemed to be
        the 'SUM INSURED' for the purpose of motor tariff and it will be fixed
        at the commencement of each policy period for each insured vehicle. The
        IDV of the vehicle is to be fixed on the basis of manufacturer's listed
        selling price of the brand and model as the vehicle proposed for
        insurance at the commencement of insurance /renewal and adjusted for
        depreciation (as per schedule specified below). The depreciation gets
        applied on the IDV in form of percentage based on the age of car as
        shown below: {"\n"}
        <BsArrowReturnRight /> Not exceeding 6 months: 5% {"\n"}
        <BsArrowReturnRight /> Exceeding 6 months but not exceeding 1 year: 15%{" "}
        {"\n"}
        <BsArrowReturnRight /> Exceeding 1 year but not exceeding 2 years: 20%{" "}
        {"\n"}
        <BsArrowReturnRight /> Exceeding 2 years but not exceeding 3 years: 30%{" "}
        {"\n"}
        <BsArrowReturnRight /> Exceeding 3 years but not exceeding 4 years: 40%{" "}
        {"\n"}
        <BsArrowReturnRight /> Exceeding 4 years but not exceeding 5 years: 50%{" "}
        {"\n"}
      </span>
    ),
  },
  {
    id: "mfaq2",
    question:
      "What is a car insurance premium? And what are the factors that affect the premium calculation for a car insurance?",
    answer: (
      <span>
        Premium is the amount which you pay to the insurance company for the
        coverage that the insurer is allowing under its car insurance policy.
        The insurance company covers specific financial risks and for covering
        your risks you have to pay a premium for the policy on an annual basis.
        The premium payable on a car depends on: {"\n"}
        <BsArrowReturnRight /> Type of vehicle {"\n"}
        <BsArrowReturnRight /> Age of vehicle {"\n"}
        <BsArrowReturnRight /> City of registration {"\n"}
        <BsArrowReturnRight /> Period of coverage (1 year or 3 year bundled
        cover for new car) {"\n"}
        <BsArrowReturnRight /> Add on covers opted {"\n"}
        <BsArrowReturnRight /> Any applicable discounts/loadings, as per the
        underwriting rules of the insurer in case of break in cases {"\n"}
      </span>
    ),
  },
  {
    id: "mfaq3",
    question: "How can I pay premium for buying car insurance online?",
    answer:
      "You can pay premium online through one of the various modes of payment offered by insurance companies. Once you have shortlisted a car insurance plan and filled the proposal form, you will be redirected to the payment page of the insurance company. You can then choose to pay through credit or debit cards, net banking, wallets or UPI.",
  },
  {
    id: "mfaq4",
    question:
      "If no claim has been made, do I still have to pay premium at the time of renewal?",
    answer:
      "Yes, you would be required to pay premium again. However you can avail the benefit of No Claim Bonus offered by your insurance company. Through this benefit, you can get discount of upto 50% on own damage component of your insurance premium depending on number of claim free years and the insurance company’s policy.",
  },
  {
    id: "mfaq5",
    question:
      "If I am using the car in a particular city, what premium rate is applied?",
    answer:
      "The premium of your car insurance policy depends on the place where the car is registered. If the car is registered in a metropolitan city, the premiums would be higher. Even if you use the car in a non-metro city while the car has the registration number of a metro city, the premium for a metro city would be applicable.",
  },
];
export const claims = [
  {
    id: "nfaq1",
    question: "What is an online car insurance claim?",
    answer:
      "Car insurance online claim is when you raise a claim on your car insurance policy online. To make a claim you can contact Fynix and the company's claim team would help you get your car insurance claims settled at the earliest. You can reachFynix at our toll free number 1800 267 6767 or drop us an emailatFynix.care@FYNIX.com and oursupport team would get in touch with you to help with your claim procedure. Alternatively, you can get your claims settled through your insurance company. You can send an email to the insurance company intimating them about your claim. You can also call the company’s claim helpline and register your claim. The company would then guide you on the process of car insurance claim settlement. A car insurance claim occurs in case of theft of the car, third party liability and if your own car is damaged in an accident or a calamity.",
  },
  {
    id: "nfaq2",
    question:
      "What documents are required to be submitted for a car insurance claim?",
    answer:
      "In case of a claim, you need to submit a claim form which should be completely filled and signed. Along with the form, the policy document, driving license, police FIR if you have suffered a third party liability or theft of the car, the keys of the car in case of theft claims and your identity proof. Thereafter, the insurance company would process your claim. If you take the vehicle to a networked garage for repairs, a claim estimate report would be prepared and submitted by the insurance company’s surveyor. All repair bills in original should also be submitted to the company for settlement of claims. You can also contact Fynix for your car insurance claims. Fynix helps its customers get their claim settlements at the earliest. Just intimate your claim to Fynix at 1800 267 2626 or by sending a mail to Fynix.care@FYNIX.com and Fynix would help guide you with the claim process and the documents that you need to submit.",
  },
  {
    id: "nfaq3",
    question:
      "What are the general steps adopted by the insurer to settle claims?",
    answer: (
      <span>
        Generally following procedure adopted by the insurers once the claim
        form is filled and filed along with the necessary documents.{"\n"}
        <BsArrowReturnRight /> The surveyor attends the claim within 24 hours
        from the time of intimation.{"\n"}
        <BsArrowReturnRight /> Take photographs, assess, estimate and inform
        assessed estimates to the User within the same day of assessment.
        {"\n"}
        <BsArrowReturnRight /> After the completion of the job, the Surveyor
        carries out re-inspection. The insured then makes payment to the
        workshop/ garage as per the surveyor's assessed estimation and releases
        a proof of release document. (The proof of release is an authenticated
        document signed by the insured to release his vehicle from the garage
        after it is checked and repaired).{"\n"}
        <BsArrowReturnRight /> Lastly, the insured submits the original bill,
        proof of release and cash receipt (derived from the garage) to the
        surveyor.{"\n"}
        <BsArrowReturnRight /> The surveyor then sends the claim file to the
        Insurance Company for settlement along with all the documents.{"\n"}
        <BsArrowReturnRight /> Insurance company then reimburses the Customer
        within seven working days from the date of receipt of claim file.
        {"\n"}
      </span>
    ),
  },
  {
    id: "nfaq4",
    question: "Is there any cashless facility available for repairs?",
    answer: (
      <span>
        Generally following procedure adopted by the insurers once the claim
        form is filled and filed along with the necessary documents.{"\n"}{" "}
        <BsArrowReturnRight /> The surveyor attends the claim within 24 hours
        from the time of intimation.{"\n"} <BsArrowReturnRight /> Take
        photographs, assess, estimate and inform assessed estimates to the User
        within the same day of assessment.{"\n"}
        <BsArrowReturnRight /> After the completion of the job, the Surveyor
        carries out re-inspection. The insured then makes payment to the
        workshop garage as per the surveyor's assessed estimation and releases a
        proof of release document. (The proof of release is an authenticated
        document signed by the insured to release his vehicle from the garage
        after it is checked and repaired).{"\n"}
        <BsArrowReturnRight /> Lastly, the insured submits the original bill,
        proof of release and cash receipt (derived from the garage) to the
        surveyor.{"\n"}
        <BsArrowReturnRight /> The surveyor then sends the claim file to the
        Insurance Company for settlement along with all the documents.{"\n"}
        <BsArrowReturnRight /> Insurance company then reimburses the Customer
        within seven working days from the date of receipt of claim file
      </span>
    ),
  },
  {
    id: "nfaq5",
    question: "What is the amount that insured would have to bear?",
    answer: (
      <span>
        Insured will have to bear the following charges:{" \n "}
        <BsArrowReturnRight /> The amount of depreciation as per the rate{" "}
        prescribed in case zero depreciation cover is not availed{"\n"}
        <BsArrowReturnRight /> Reasonable value of salvage{"\n"}
        <BsArrowReturnRight /> Compulsory and voluntary deductions under the
        {"\n"}
        policy, if insured have opted for it.{"\n"}
      </span>
    ),
  },
];
