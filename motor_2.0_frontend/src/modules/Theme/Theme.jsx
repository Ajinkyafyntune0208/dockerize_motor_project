import React, { useEffect, useRef, useState } from "react";
import { Card } from "components";
import { useForm, Controller } from "react-hook-form";
import styled from "styled-components";
import { useDispatch } from "react-redux";
import { themeConfig } from "modules/login/login.slice";
import _ from "lodash";
import Select from "react-select";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import swal from "sweetalert";
import ThemePreview from "./theme-preview";
import { type } from "modules/Home/home.slice";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const Theme = () => {
  const { register, handleSubmit, watch, control, reset } = useForm({
    defaultValues: !_.isEmpty(theme) ? theme : {},
  });
  const dispatch = useDispatch();
  const [preview, setPreview] = useState(false);
  const previewRef = useRef(null);

  useEffect(() => {
    reset(theme);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [theme]);

  const onSubmit = (data) => {
    dispatch(themeConfig({ theme_config: JSON.stringify(data), type: "save" }));
    swal("Success", "Theme Updated Successfully", "success").then(() =>
      window.location.reload()
    );
  };

  const handleReset = () => {
    dispatch(themeConfig({ theme_config: JSON.stringify({}), type: "save" }));
    swal("Success", "Theme Reset Successfully", "success").then(() =>
      window.location.reload()
    );
  };

  const primary = watch("primaryColor.color");
  const secondary = watch("floatButton.floatColor");
  const ternary = watch("leadPageBtn.background1");
  const quaternary = watch("leadPageBtn.background2");
  const fonts = watch("QuoteBorderAndFont.fontFamily");

  const handlePreview = () => {
    setPreview(true);
  };

  useEffect(() => {
    if (preview) {
      // Scroll to the preview component
      previewRef.current.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }
  }, [preview]);
  const inputField = (label, name) => {
    return (
      <div className="mr-5">
        <Label>{label}</Label>
        <Input type="color" ref={register} name={name} />
      </div>
    );
  };

  const MultiSelect = (label, name, defaultValue) => {
    const options = [
      { value: "lato", label: "Lato" },
      {
        value: "pfhandbooksans_regular",
        label: "Pfhandbooksans Regular",
      },
      {
        value: "pfhandbooksans_medium",
        label: "Pfhandbooksans Medium",
      },
      { value: "sans-serif", label: "Sans Serif" },
      { value: "quickSand", label: "Quick Sand" },
      { value: "roboto", label: "Roboto" },
      { value: "Poppins", label: "Poppins" },
      { value: "Montserrat", label: "Montserrat" },
      { value: "Arial", label: "Arial" },
      { value: "basiersquaremed", label: "Basiers Quaremed" },
      { value: "Manrope", label: "Manrope" },
    ];

    return (
      <div className="mr-5">
        <Label>{label}</Label>
        <Container>
          <Controller
            name={name}
            control={control}
            render={({ field, onChange, onBlur, value, name }) => (
              <Select
                name={name}
                onChange={(selectedOptions) => {
                  const selectedValues = Array.isArray(selectedOptions)
                    ? selectedOptions.map((option) => option.value)
                    : [selectedOptions.value];
                  onChange(selectedValues);
                }}
                onBlur={onBlur}
                ref={register}
                value={options.find((option) => option.value === value)}
                defaultValue={defaultValue || ""}
                className="selectOption"
                {...field}
                options={options}
              />
            )}
          />
        </Container>
      </div>
    );
  };

  const MultiSelectVariant = (label, name, defaultValue) => {
    const options = [
      { value: "primary", label: "primary" },
      {
        value: "secondary",
        label: "secondary",
      },
      {
        value: "success",
        label: "success",
      },
      { value: "warning", label: "warning" },
      { value: "danger", label: "danger" },
      { value: "info", label: "info" },
      { value: "light", label: "light" },
      { value: "dark", label: "dark" },
      { value: "link", label: "link" },
    ];

    return (
      <div className="mr-5">
        <Label>{label}</Label>
        <Container>
          <Controller
            name={name}
            control={control}
            render={({ field, onChange, onBlur, value, name }) => (
              <Select
                name={name}
                onChange={(selectedOptions) => {
                  const selectedValues = Array.isArray(selectedOptions)
                    ? selectedOptions.map((option) => option.value)
                    : [selectedOptions.value];
                  onChange(selectedValues);
                }}
                onBlur={onBlur}
                ref={register}
                value={options.find((option) => option.value === value)}
                defaultValue={defaultValue || ""}
                className="selectOption"
                {...field}
                options={options}
              />
            )}
          />
        </Container>
      </div>
    );
  };

  const MultiSelectVariantOutline = (label, name, defaultValue) => {
    const options = [
      { value: "outline-primary", label: "outline-primary" },
      {
        value: "outline-secondary",
        label: "outline-secondary",
      },
      {
        value: "outline-success",
        label: "outline-success",
      },
      { value: "outline-warning", label: "outline-warning" },
      { value: "outline-danger", label: "outline-danger" },
      { value: "outline-info", label: "outline-info" },
      { value: "outline-light", label: "outline-light" },
      { value: "outline-dark", label: "outline-dark" },
      { value: "outline-link", label: "outline-link" },
    ];

    return (
      <div className="mr-5">
        <Label>{label}</Label>
        <Container>
          <Controller
            name={name}
            control={control}
            render={({ field, onChange, onBlur, value, name }) => (
              <Select
                name={name}
                onChange={(selectedOptions) => {
                  const selectedValues = Array.isArray(selectedOptions)
                    ? selectedOptions.map((option) => option.value)
                    : [selectedOptions.value];
                  onChange(selectedValues);
                }}
                onBlur={onBlur}
                ref={register}
                value={options.find((option) => option.value === value)}
                defaultValue={defaultValue || ""}
                className="selectOption"
                {...field}
                options={options}
              />
            )}
          />
        </Container>
      </div>
    );
  };

  const HiddenInput = (name, defaultValue) => {
    return (
      <Input
        type="hidden"
        ref={register}
        name={name}
        defaultValue={defaultValue}
      />
    );
  };

  return (
    <>
      <h2 className="text-center my-5">Theme Colors</h2>
      <form onSubmit={handleSubmit(onSubmit)}>
        {/* Proposal Card Start */}
        <Card title={"Theme"}>
          <Container>
            {inputField("Primary", "primaryColor.color")}
            {inputField("Secondary", "floatButton.floatColor")}
            {inputField("Ternary", "leadPageBtn.background1")}
            {inputField("Quaternary", "leadPageBtn.background2")}
            {MultiSelectVariant("ButtonVariant", "buttonVariantScheme")}
            {MultiSelectVariantOutline(
              "Outline-Button Variant",
              "outlineButtonVariantScheme"
            )}
            {MultiSelect(
              "Font Family",
              "QuoteBorderAndFont.fontFamily",
              watch("QuoteBorderAndFont.fontFamily")
            )}
            {/* proposal summery */}
            {HiddenInput(
              "proposalCardSummary.background",
              `linear-gradient(90deg,${
                watch("primaryColor.color") || "#95181A"
              } 0%,${watch("primaryColor.color") || "#95181A"} 100%)`
            )}
            {/* proposal summery active*/}
            {HiddenInput(
              "proposalCardActive.background",
              `linear-gradient(90deg,${
                watch("primaryColor.color") || "#95181A"
              } 0%,${watch("primaryColor.color") || "#95181A"} 100%)`
            )}
            {/* proposal Proceed Btn */}
            {HiddenInput(
              "proposalProceedBtn.hex1",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "proposalProceedBtn.hex2",
              watch("primaryColor.color")
            )}
            {/* gender Proposal */}
            {HiddenInput(
              "genderProposal.background",
              `radial-gradient(circle,${
                watch("primaryColor.color") || "rgb(199 34 42)"
              } 17%,${watch("primaryColor.color") || "rgb(179 45 51)"} 85%)`
            )}
            {HiddenInput(
              "genderProposal.boxShadow",
              theme?.genderProposal?.boxShadow
            )}
            {/* question proposal  */}
            {HiddenInput(
              "questionsProposal.color",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "questionsProposal.toggleBackgroundColor",
              watch("primaryColor.color")
            )}
            {/* side card proposal  */}
            {HiddenInput(
              "sideCardProposal.icon",
              theme?.sideCardProposal?.icon
            )}
            {HiddenInput("sideCardProposal.badge", "sideCardProposal.badge")}
            {HiddenInput(
              "sideCardProposal.editBadge",
              theme?.sideCardProposal?.editBadge
            )}
            {HiddenInput(
              "sideCardProposal.linkColor",
              theme?.sideCardProposal?.linkColor
            )}
            {HiddenInput(
              "sideCardProposal.iconColor",
              watch("primaryColor.color")
            )}
            {/* buttons  */}
            {HiddenInput(
              "Button.default.background",
              theme?.Button?.default?.background
            )}
            {HiddenInput(
              "Button.default.border_color",
              theme?.Button?.default?.border_color
            )}
            {HiddenInput(
              "Button.default.text_color",
              theme?.Button?.default?.text_color
            )}
            {HiddenInput(
              "Button.danger.background",
              theme?.Button?.danger?.background
            )}
            {HiddenInput(
              "Button.danger.border_color",
              theme?.Button?.danger?.border_color
            )}
            {HiddenInput(
              "Button.danger.text_color",
              theme?.Button?.danger?.text_color
            )}
            {HiddenInput(
              "Button.warning.background",
              theme?.Button?.warning?.background
            )}
            {HiddenInput(
              "Button.warning.border_color",
              theme?.Button?.warning?.border_color
            )}
            {HiddenInput(
              "Button.warning.text_color",
              theme?.Button?.warning?.text_color
            )}
            {HiddenInput(
              "Button.outline.background",
              theme?.Button?.outline?.background
            )}
            {HiddenInput(
              "Button.outline.border_color",
              theme?.Button?.outline?.border_color
            )}
            {HiddenInput(
              "Button.outline.text_color",
              theme?.Button?.outline?.text_color
            )}
            {HiddenInput(
              "Button.square_outline.background",
              theme?.Button?.square_outline?.background
            )}
            {HiddenInput(
              "Button.square_outline.border_color",
              theme?.Button?.square_outline?.border_color
            )}
            {HiddenInput(
              "Button.square_outline.text_color",
              theme?.Button?.square_outline?.text_color
            )}
            {HiddenInput(
              "Button.outline_secondary.background",
              theme?.Button?.outline_secondary?.background
            )}
            {HiddenInput(
              "Button.outline_secondary.border_color",
              theme?.Button?.outline_secondary?.border_color
            )}
            {HiddenInput(
              "Button.outline_secondary.text_color",
              theme?.Button?.outline_secondary?.text_color
            )}
            {HiddenInput(
              "Button.submit_disabled.background",
              theme?.Button?.submit_disabled?.background
            )}
            {HiddenInput(
              "Button.submit_disabled.border_color",
              theme?.Button?.submit_disabled?.border_color
            )}
            {HiddenInput(
              "Button.submit_disabled.text_color",
              theme?.Button?.submit_disabled?.text_color
            )}
            {HiddenInput(
              "Button.outline_solid.background",
              theme?.Button?.outline_solid?.background
            )}
            {HiddenInput(
              "Button.outline_solid.border_color",
              theme?.Button?.outline_solid?.border_color
            )}
            {HiddenInput(
              "Button.outline_solid.text_color",
              theme?.Button?.outline_solid?.text_color
            )}
            {/* check circle  */}
            {HiddenInput(
              "CheckCircle.backgroundImage",
              theme?.CheckCircle?.backgroundImage
            )}
            {/* Tab  */}
            {HiddenInput("Tab.color", watch("primaryColor.color"))}
            {HiddenInput(
              "Tab.border",
              `1px solid ${watch("primaryColor.color") || "#C7222A"}`
            )}
            {/* check box  */}
            {HiddenInput(
              "CheckBox.color",
              watch("primaryColor.color") || theme?.CheckBox?.color
            )}
            {HiddenInput(
              "CheckBox.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {/* Header  */}
            {HiddenInput("Header.color", watch("primaryColor.color"))}
            {HiddenInput("Header.hex1", watch("primaryColor.color"))}
            {HiddenInput("Header.hex2", watch("primaryColor.color"))}
            {HiddenInput(
              "Header.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "Header.borderLogo",
              `2.5px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {/* filter container  */}
            {HiddenInput("FilterConatiner.color", watch("primaryColor.color"))}
            {HiddenInput(
              "FilterConatiner.lightColor",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "FilterConatiner.lightBorder",
              `0.5px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "FilterConatiner.lightBorder1",
              `0.5px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "FilterConatiner.editIconColor",
              watch("floatButton.floatColor")
            )}
            {HiddenInput(
              "FilterConatiner.clearAllIcon",
              theme?.FilterConatiner?.clearAllIcon
            )}
            {HiddenInput(
              "FilterConatiner.clearAllTextColor",
              watch("leadPageBtn.background1")
            )}
            {/* quote card  */}
            {HiddenInput("QuoteCard.color", watch("primaryColor.color"))}
            {HiddenInput("QuoteCard.color2", watch("primaryColor.color"))}
            {HiddenInput("QuoteCard.color3", watch("primaryColor.color"))}
            {HiddenInput(
              "QuoteCard.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "QuoteCard.border2",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "QuoteCard.mouseHoverShadow",
              `0px 0px 8px 0px ${watch("primaryColor.color") || "#DA9089"}`
            )}
            {HiddenInput(
              "QuoteCard.borderCheckBox",
              theme?.QuoteCard?.borderCheckBox
            )}
            {HiddenInput("QuoteCard.boxShadow", "QuoteCard.boxShadow")}
            {HiddenInput(
              "QuoteCard.ribbonBackground",
              theme?.QuoteCard?.ribbonBackground
            )}
            {/* QuotePopups  */}
            {HiddenInput("QuotePopups.color", watch("primaryColor.color"))}
            {HiddenInput("QuotePopups.color2", watch("primaryColor.color"))}
            {HiddenInput(
              "QuotePopups.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "QuotePopups.border2",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "QuotePopups.border3",
              `2px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput("QuotePopups.hex1", watch("primaryColor.color"))}
            {HiddenInput("QuotePopups.hex2", watch("primaryColor.color"))}
            {HiddenInput(
              "QuotePopups.prevpopBorder",
              `33px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {/* QuoteBorderAndFont */}
            {HiddenInput(
              "QuoteBorderAndFont.navColor",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "QuoteBorderAndFont.checkBoxAndToggleBackground",
              theme?.QuoteBorderAndFont?.checkBoxAndToggleBackground
            )}
            {HiddenInput(
              "QuoteBorderAndFont.journeyCategoryButtonColor",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "QuoteBorderAndFont.fontFamilyBold",
              watch("QuoteBorderAndFont.fontFamily")
            )}
            {HiddenInput(
              "QuoteBorderAndFont.linkColor",
              watch("primaryColor.color")
            )}
            {/* ReviewCard */}
            {HiddenInput("ReviewCard.color", "ReviewCard.color")}
            {HiddenInput("ReviewCard.border", "ReviewCard.border")}
            {HiddenInput("ReviewCard.borderDashed", "ReviewCard.borderDashed")}
            {HiddenInput("ReviewCard.color2", "ReviewCard.color2")}
            {/* avatar */}
            {HiddenInput(
              "avatar.border",
              `2px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {/* reactCalendar */}
            {HiddenInput("reactCalendar.selected", watch("primaryColor.color"))}
            {HiddenInput(
              "reactCalendar.background",
              watch("primaryColor.color")
            )}
            {/* leadPageBtn */}

            {HiddenInput("leadPageBtn.background3", "leadPageBtn.background3")}
            {HiddenInput("leadPageBtn.link", "leadPageBtn.link")}
            {HiddenInput("leadPageBtn.linkColor", "leadPageBtn.linkColor")}
            {HiddenInput(
              "leadPageBtn.borderRadius",
              theme?.leadPageBtn?.borderRadius
            )}
            {/* Registration */}
            {HiddenInput(
              "Registration.proceedBtn.background",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "Registration.proceedBtn.color",
              theme?.Registration?.proceedBtn?.color
            )}
            {HiddenInput(
              "journeyType.buttonVariant",
              watch("buttonVariantScheme")?.[0] || "primary"
            )}
            {HiddenInput(
              "journeyType.outlineVariant",
              watch("outlineButtonVariantScheme")?.[0] || "outline-primary"
            )}
            {HiddenInput("Registration.otherBtn.hex1", primary)}
            {HiddenInput("Registration.otherBtn.hex2", primary)}
            {/* VehicleType */}
            {HiddenInput(
              "VehicleType.buttonVariant",
              theme?.journeyType?.buttonVariant
            )}
            {HiddenInput(
              "VehicleType.outlineVariant",
              theme?.journeyType?.outlineVariant
            )}
            {HiddenInput(
              "VehicleType.filterIconCol",
              theme?.VehicleType?.filterIconCol
            )}
            {/* Stepper  */}
            {HiddenInput(
              "Stepper.stepperColor.background",
              watch("primaryColor.color")
            )}
            {HiddenInput("Stepper.linkColor", watch("Stepper.linkColor"))}
            {/* Title  */}
            {HiddenInput("Tile.color", watch("primaryColor.color"))}
            {HiddenInput(
              "Tile.border",
              `2px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "Tile.boxShadow",
              `0px 0px 7px 0px ${watch("primaryColor.color") || "#C7222A"}`
            )}
            {/* VehicleProceed */}

            {HiddenInput("VehicleProceed.hex1", theme?.VehicleProceed?.hex1)}
            {HiddenInput("VehicleProceed.hex2", theme?.VehicleProceed?.hex2)}
            {/* journeyType */}
            {HiddenInput(
              "journeyType.buttonVariant",
              theme?.journeyType?.buttonVariant
            )}
            {HiddenInput(
              "journeyType.outlineVariant",
              theme?.journeyType?.outlineVariant
            )}
            {HiddenInput(
              "journeyType.filterIconCol",
              theme?.journeyType?.filterIconCol
            )}
            {/* toggleModal */}
            {HiddenInput("toggleModal.color", theme?.toggleModal?.color)}
            {HiddenInput(
              "toggleModal.walletImage",
              theme?.toggleModal?.walletImage
            )}
            {/* comparePage */}
            {HiddenInput("comparePage.color", watch("primaryColor.color"))}
            {HiddenInput(
              "comparePage.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "comparePage.textColor",
              watch("comparePage.textColor")
            )}
            {/* paymentConfirmation */}
            {HiddenInput(
              "paymentConfirmation.headers",
              `linear-gradient(81.67deg,${watch(
                "primaryColor.color"
              )} 0%,#ffffcc 100%)`
            )}
            {HiddenInput(
              "paymentConfirmation.Button.hex1",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "paymentConfirmation.Button.hex2",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "paymentConfirmation.table",
              theme?.paymentConfirmation?.table
            )}
            {HiddenInput(
              "paymentConfirmation.buttonTextColor",
              theme?.paymentConfirmation?.buttonTextColor
            )}
            {/* CallUs */}
            {HiddenInput("CallUs.icon", watch("primaryColor.color"))}
            {/* PaymentStatus */}
            {HiddenInput("PaymentStatus.hex1", watch("PaymentStatus.hex1"))}
            {HiddenInput("PaymentStatus.hex2", watch("primaryColor.color"))}
            {/* MultiSelect */}
            {HiddenInput("MultiSelect.color", watch("primaryColor.color"))}
            {/* proposalHeader */}
            {HiddenInput("proposalHeader.color", watch("primaryColor.color"))}
            {/* comparePage2 */}
            {HiddenInput(
              "comparePage2.background",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "comparePage2.borderBottom",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "comparePage2.iconsColor",
              watch("primaryColor.color")
            )}
            {HiddenInput(
              "comparePage2.borderHeader",
              `5px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "comparePage2.lg",
              `-webkit-linear-gradient(-134deg, #ffffff, ${watch(
                "primaryColor.color"
              )})`
            )}
            {/* CardPop */}
            {HiddenInput("CardPop.background", watch("CardPop.background"))}
            {HiddenInput(
              "CardPop.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {/* NoPlanCard */}
            {HiddenInput(
              "NoPlanCard.background",
              theme?.NoPlanCard?.background
            )}
            {HiddenInput(
              "NoPlanCard.background1",
              theme?.NoPlanCard?.background1
            )}
            {HiddenInput(
              "NoPlanCard.border",
              `2px dotted ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput(
              "NoPlanCard.border1",
              `2px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {/* prevPolicy */}
            {HiddenInput("prevPolicy.color1", watch("primaryColor.color"))}
            {HiddenInput("prevPolicy.color2", watch("primaryColor.color"))}
            {HiddenInput("prevPolicy.boxShadow", theme?.prevPolicy?.boxShadow)}
            {/* LandingPage */}
            {HiddenInput("LandingPage.color", watch("primaryColor.color"))}
            {HiddenInput(
              "LandingPage.loginBtnColor",
              watch("primaryColor.color")
            )}
            {HiddenInput("LandingPage.color2", theme?.LandingPage?.color2)}
            {HiddenInput("LandingPage.color3", theme?.LandingPage?.color3)}
            {HiddenInput("LandingPage.filter", theme?.LandingPage?.filter)}
            {/* Payment */}
            {HiddenInput("Payment.color", watch("primaryColor.color"))}
            {/* City */}
            {HiddenInput("City.color", "City.color")}
            {HiddenInput("City.background", watch("primaryColor.color"))}
            {HiddenInput(
              "City.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {/* Home */}
            {HiddenInput("Home.backgroundColor", theme?.Home?.backgroundColor)}
            {/* regularFont */}
            {HiddenInput(
              "regularFont.fontColor",
              watch("floatButton.floatColor")
            )}
            {HiddenInput(
              "regularFont.fontFamily",
              watch("QuoteBorderAndFont.fontFamily")
            )}
            {HiddenInput(
              "regularFont.headerFontFamily",
              watch("QuoteBorderAndFont.fontFamily")
            )}
            {HiddenInput(
              "regularFont.textColor",
              theme?.regularFont?.textColor
            )}
            {HiddenInput(
              "regularFont.fontWeight",
              theme?.regularFont?.fontWeight
            )}
            {/* mediumFont */}
            {HiddenInput(
              "mediumFont.fontFamily",
              watch("QuoteBorderAndFont.fontFamily")
            )}
            {/* floatButton */}

            {HiddenInput(
              "floatButton.floatBorder",
              `1px solid ${watch("floatButton.floatColor") || "#000000"}`
            )}
            {/* boldBorder */}

            {HiddenInput(
              "boldBorder.border",
              `1px solid ${watch("primaryColor.color") || "#000000"}`
            )}
            {HiddenInput("boldBorder.boxShadow", theme?.boldBorder?.boxShadow)}
            {/* headings */}
            {HiddenInput("headings.fontSize", theme?.headings?.fontSize)}
            {HiddenInput("headings.textColor", theme?.headings?.textColor)}
            {/* links  */}
            {HiddenInput("links.color", theme?.links?.color)}
          </Container>

          <div style={{ marginTop: "50px", display: "flex", gap: "25px" }}>
            <SubmitBtn type="button" onClick={handlePreview}>
              Preview
            </SubmitBtn>
            <SubmitBtn type="submit">Apply</SubmitBtn>
            <SubmitBtn type="button" onClick={handleReset}>
              Reset
            </SubmitBtn>
          </div>
        </Card>
      </form>
      {preview && (
        <div ref={previewRef}>
          <ThemePreview
            primary={primary}
            secondary={secondary}
            ternary={ternary}
            quaternary={quaternary}
            fonts={fonts}
          />
        </div>
      )}
    </>
  );
};

export default Theme;

const Container = styled.div`
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  .selectOption {
    width: 200px;
  }
`;

const SubmitBtn = styled.button`
  padding: 8px 30px;
  border: none;
  background: ${({ theme }) => theme?.primaryColor?.color};
  color: #fff;
  border-radius: 20px;
`;

const Label = styled.label`
  display: block;
  font-weight: 400;
`;

const Input = styled.input`
  width: 200px;
  height: 50px;
  cursor: pointer;
  border-radius: 0 15px 0 15px;
`;
